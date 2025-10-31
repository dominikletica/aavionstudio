<?php

declare(strict_types=1);

namespace App\Tests\Theme;

use App\Theme\ThemeStateRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class ThemeStateRepositoryTest extends TestCase
{
    private Connection $connection;
    private ThemeStateRepository $repository;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement('CREATE TABLE app_theme_state (name VARCHAR(190) PRIMARY KEY, enabled INTEGER NOT NULL, active INTEGER NOT NULL, metadata TEXT NOT NULL, updated_at DATETIME NOT NULL)');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->executeStatement('INSERT INTO app_theme_state (name, enabled, active, metadata, updated_at) VALUES (:name, 1, 1, :metadata, :updated_at)', [
            'name' => 'base',
            'metadata' => json_encode(['locked' => true], JSON_THROW_ON_ERROR),
            'updated_at' => $now,
        ]);

        $this->repository = new ThemeStateRepository($this->connection);
    }

    public function testActivateSwitchesSingleActiveTheme(): void
    {
        $this->repository->setEnabled('ocean', true);
        $this->repository->activate('ocean');

        $activeCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_theme_state WHERE active = 1');
        self::assertSame(1, $activeCount);

        $activeSlug = (string) $this->connection->fetchOne('SELECT name FROM app_theme_state WHERE active = 1');
        self::assertSame('ocean', $activeSlug);
    }

    public function testDisablingThemeKeepsMetadata(): void
    {
        $metadata = ['locked' => false, 'custom' => true];
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->executeStatement('INSERT INTO app_theme_state (name, enabled, active, metadata, updated_at) VALUES (:name, 1, 0, :metadata, :updated_at)', [
            'name' => 'aurora',
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'updated_at' => $now,
        ]);

        $this->repository->setEnabled('aurora', false);

        $row = $this->connection->fetchAssociative('SELECT metadata FROM app_theme_state WHERE name = :name', ['name' => 'aurora']);
        self::assertNotFalse($row);
        $stored = json_decode((string) $row['metadata'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($metadata, $stored);
    }
}
