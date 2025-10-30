<?php

declare(strict_types=1);

namespace App\Doctrine\Health;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SqlitePlatform;

final class SqliteHealthChecker
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $userDatabasePath,
    ) {
    }

    public function check(): SqliteHealthReport
    {
        $platform = $this->connection->getDatabasePlatform();

        if (!$platform instanceof SqlitePlatform) {
            return new SqliteHealthReport(
                primaryPath: (string) ($this->connection->getParams()['path'] ?? ''),
                secondaryPath: $this->userDatabasePath,
                primaryExists: true,
                secondaryExists: true,
                secondaryAttached: true,
                busyTimeoutMs: 0,
            );
        }

        $params = $this->connection->getParams();
        $primaryPath = (string) ($params['path'] ?? '');

        $primaryExists = $primaryPath !== '' && file_exists($primaryPath);
        $secondaryExists = $this->userDatabasePath !== '' && file_exists($this->userDatabasePath);

        $databases = $this->connection->fetchAllAssociative('PRAGMA database_list');
        $secondaryAttached = \in_array('user_brain', array_column($databases, 'name'), true);

        $busyTimeout = (int) $this->connection->fetchOne('PRAGMA busy_timeout');

        return new SqliteHealthReport(
            primaryPath: $primaryPath,
            secondaryPath: $this->userDatabasePath,
            primaryExists: $primaryExists,
            secondaryExists: $secondaryExists,
            secondaryAttached: $secondaryAttached,
            busyTimeoutMs: $busyTimeout,
        );
    }
}
