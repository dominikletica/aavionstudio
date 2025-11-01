<?php

declare(strict_types=1);

namespace App\Doctrine\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware as DriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Result;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

final class AttachUserDatabaseMiddleware implements DriverMiddleware
{
    public function __construct(
        private readonly string $userDatabasePath,
        private readonly Filesystem $filesystem,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $busyTimeoutMs = 5000,
    ) {
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this->userDatabasePath, $this->filesystem, $this->logger, $this->busyTimeoutMs) extends AbstractDriverMiddleware {
            public function __construct(
                Driver $driver,
                private readonly string $userDatabasePath,
                private readonly Filesystem $filesystem,
                private readonly ?LoggerInterface $logger,
                private readonly int $busyTimeoutMs,
            ) {
                parent::__construct($driver);
            }

            public function connect(array $params): DriverConnection
            {
                $connection = parent::connect($params);

                if (! $this->supportsSqlite($params) || $this->userDatabasePath === '') {
                    return $connection;
                }

                $this->filesystem->mkdir(\dirname($this->userDatabasePath));

                if ($this->busyTimeoutMs > 0) {
                    try {
                        $connection->exec(\sprintf('PRAGMA busy_timeout = %d', $this->busyTimeoutMs));
                    } catch (\Throwable $exception) {
                        $this->logger?->warning('Failed to set SQLite busy_timeout', [
                            'timeout' => $this->busyTimeoutMs,
                            'exception' => $exception,
                        ]);
                    }
                }

                try {
                    $connection->exec('PRAGMA foreign_keys = ON');
                } catch (\Throwable $exception) {
                    $this->logger?->warning('Failed to enable SQLite foreign_keys', [
                        'exception' => $exception,
                    ]);
                }

                if (! \is_file($this->userDatabasePath)) {
                    $this->filesystem->touch($this->userDatabasePath);
                }

                if ($this->isAlreadyAttached($connection)) {
                    return $connection;
                }

                $quotedPath = \str_replace("'", "''", $this->userDatabasePath);

                try {
                    $connection->exec(\sprintf("ATTACH DATABASE '%s' AS user_brain", $quotedPath));
                } catch (\Throwable $exception) {
                    $this->logger?->error('Failed to attach user_brain database', [
                        'path' => $this->userDatabasePath,
                        'exception' => $exception,
                    ]);

                    return $connection;
                }

                try {
                    $connection->query('SELECT name FROM user_brain.sqlite_master LIMIT 1');
                } catch (\Throwable $exception) {
                    $this->logger?->error('Attached user_brain database validation failed', [
                        'path' => $this->userDatabasePath,
                        'exception' => $exception,
                    ]);
                }

                return $connection;
            }

            /**
             * @param array<string,mixed> $params
             */
            private function supportsSqlite(array $params): bool
            {
                if (isset($params['driver']) && \is_string($params['driver']) && \str_contains($params['driver'], 'sqlite')) {
                    return true;
                }

                if (isset($params['driverClass']) && \is_string($params['driverClass']) && \str_contains($params['driverClass'], 'SQLite')) {
                    return true;
                }

                if (isset($params['url']) && \is_string($params['url']) && \str_starts_with($params['url'], 'sqlite')) {
                    return true;
                }

                return false;
            }

            private function isAlreadyAttached(DriverConnection $connection): bool
            {
                try {
                    $result = $connection->query('PRAGMA database_list');
                } catch (\Throwable $exception) {
                    $this->logger?->warning('Unable to inspect SQLite database list', [
                        'exception' => $exception,
                    ]);

                    return false;
                }

                if (! $result instanceof Result) {
                    return false;
                }

                try {
                    while ($row = $result->fetchAssociative()) {
                        if (($row['name'] ?? null) === 'user_brain') {
                            return true;
                        }
                    }
                } finally {
                    $result->free();
                }

                return false;
            }
        };
    }
}
