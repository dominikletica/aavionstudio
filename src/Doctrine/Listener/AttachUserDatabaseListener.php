<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Ensures that the secondary SQLite database ("user.brain") is available
 * whenever Doctrine opens the primary connection.
 */
final class AttachUserDatabaseListener
{
    public function __construct(
        private readonly string $userDatabasePath,
        private readonly Filesystem $filesystem,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $busyTimeoutMs = 5000,
    ) {
    }

    public function postConnect(ConnectionEventArgs $event): void
    {
        $connection = $event->getConnection();
        $platform = $connection->getDatabasePlatform();

        if (!$platform instanceof SqlitePlatform) {
            return;
        }

        if ($this->userDatabasePath === '') {
            $this->logger?->warning('user.brain path is empty; skipping ATTACH DATABASE.');

            return;
        }

        $this->filesystem->mkdir(\dirname($this->userDatabasePath));

        if ($this->busyTimeoutMs > 0) {
            try {
                $connection->executeStatement(\sprintf('PRAGMA busy_timeout = %d', $this->busyTimeoutMs));
            } catch (\Throwable $exception) {
                $this->logger?->warning('Failed to set SQLite busy_timeout', [
                    'timeout' => $this->busyTimeoutMs,
                    'exception' => $exception,
                ]);
            }
        }

        try {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        } catch (\Throwable $exception) {
            $this->logger?->warning('Failed to enable SQLite foreign_keys', [
                'exception' => $exception,
            ]);
        }

        if (!is_file($this->userDatabasePath)) {
            $this->filesystem->touch($this->userDatabasePath);
        }

        $databases = $connection->fetchAllAssociative('PRAGMA database_list');

        foreach ($databases as $database) {
            if (($database['name'] ?? null) === 'user_brain') {
                return;
            }
        }

        $quotedPath = str_replace("'", "''", $this->userDatabasePath);

        $connection->executeStatement(\sprintf("ATTACH DATABASE '%s' AS user_brain", $quotedPath));

        try {
            $connection->executeQuery("SELECT name FROM user_brain.sqlite_master LIMIT 1");
        } catch (\Throwable $exception) {
            $this->logger?->error('Attached user_brain database check failed', [
                'path' => $this->userDatabasePath,
                'exception' => $exception,
            ]);
        }
    }
}
