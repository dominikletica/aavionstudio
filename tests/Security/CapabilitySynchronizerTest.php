<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Module\ModuleRegistry;
use App\Security\Capability\CapabilityRegistry;
use App\Security\Capability\CapabilitySynchronizer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class CapabilitySynchronizerTest extends TestCase
{
    private Connection $connection;
    private CapabilitySynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement('CREATE TABLE app_role_capability (role_name VARCHAR(64) NOT NULL, capability VARCHAR(190) NOT NULL, PRIMARY KEY (role_name, capability))');
        $this->connection->executeStatement('CREATE TABLE app_audit_log (id CHAR(26) PRIMARY KEY, actor_id CHAR(26), action VARCHAR(128) NOT NULL, subject_id CHAR(26), context TEXT NOT NULL, ip_hash VARCHAR(128), occurred_at DATETIME NOT NULL)');

        $registry = new CapabilityRegistry(new ModuleRegistry([
            [
                'slug' => 'core',
                'name' => 'Core',
                'description' => 'Core services',
                'base_path' => __DIR__,
                'priority' => 0,
                'navigation' => [],
                'capabilities' => [
                    [
                        'key' => 'content.publish',
                        'label' => 'Publish Content',
                        'default_roles' => ['ROLE_EDITOR', 'ROLE_ADMIN'],
                    ],
                ],
                'metadata' => [],
                'enabled' => true,
            ],
        ]));

        $this->synchronizer = new CapabilitySynchronizer(
            $this->connection,
            $registry,
        );
    }

    public function testSynchronizeInsertsDefaultRoleCapabilities(): void
    {
        $this->synchronizer->synchronize();

        $rows = $this->connection->fetchAllAssociative('SELECT role_name, capability FROM app_role_capability ORDER BY role_name');

        self::assertSame([
            ['role_name' => 'ROLE_ADMIN', 'capability' => 'content.publish'],
            ['role_name' => 'ROLE_EDITOR', 'capability' => 'content.publish'],
        ], $rows);

        $audit = $this->connection->fetchAllAssociative('SELECT action, context FROM app_audit_log ORDER BY action');
        self::assertCount(2, $audit);
    }

    public function testSynchronizeIsIdempotent(): void
    {
        $this->synchronizer->synchronize();
        $this->synchronizer->synchronize();

        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_role_capability');
        self::assertSame(2, $count);
    }
}
