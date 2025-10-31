<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityAuditControllerTest extends WebTestCase
{
    private Connection $connection;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        static::bootKernel();
        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);

        $this->connection->executeStatement('PRAGMA foreign_keys = OFF');

        foreach (['app_audit_log', 'app_user', 'app_user_role', 'app_role'] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $this->connection->executeStatement('CREATE TABLE app_user (id CHAR(26) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(190) NOT NULL, locale VARCHAR(12) NOT NULL, timezone VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT "active", flags TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_role (name VARCHAR(64) PRIMARY KEY, label VARCHAR(190) NOT NULL, is_system INTEGER NOT NULL DEFAULT 1, metadata TEXT NOT NULL DEFAULT "{}")');
        $this->connection->executeStatement('CREATE TABLE app_user_role (user_id CHAR(26) NOT NULL, role_name VARCHAR(64) NOT NULL, assigned_at DATETIME NOT NULL, assigned_by CHAR(26), PRIMARY KEY (user_id, role_name))');
        $this->connection->executeStatement('CREATE TABLE app_audit_log (id CHAR(26) PRIMARY KEY, actor_id CHAR(26), action VARCHAR(128) NOT NULL, subject_id CHAR(26), context TEXT NOT NULL, ip_hash VARCHAR(128), occurred_at DATETIME NOT NULL)');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->insert('app_role', [
            'name' => 'ROLE_ADMIN',
            'label' => 'Administrator',
            'is_system' => 1,
            'metadata' => '{}',
        ]);

        $this->connection->insert('app_user', [
            'id' => '01HXADMINUSER0000000000000',
            'email' => 'admin@example.com',
            'password_hash' => password_hash('secret123', PASSWORD_BCRYPT),
            'display_name' => 'Admin',
            'locale' => 'en',
            'timezone' => 'UTC',
            'status' => 'active',
            'flags' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
            'last_login_at' => $now,
        ]);

        $this->connection->insert('app_user_role', [
            'user_id' => '01HXADMINUSER0000000000000',
            'role_name' => 'ROLE_ADMIN',
            'assigned_at' => $now,
            'assigned_by' => null,
        ]);

        $this->connection->insert('app_audit_log', [
            'id' => '01AUDITLOGENTRY0000000000000',
            'actor_id' => '01HXADMINUSER0000000000000',
            'action' => 'user.role.assigned',
            'subject_id' => '01HXUSER000000000000000000',
            'context' => json_encode(['role' => 'ROLE_EDITOR'], JSON_THROW_ON_ERROR),
            'ip_hash' => null,
            'occurred_at' => $now,
        ]);

        $this->connection->insert('app_audit_log', [
            'id' => '01AUDITLOGENTRY0000000000001',
            'actor_id' => null,
            'action' => 'system.cache.cleared',
            'subject_id' => null,
            'context' => json_encode(['source' => 'maintenance'], JSON_THROW_ON_ERROR),
            'ip_hash' => null,
            'occurred_at' => (new \DateTimeImmutable('-5 minutes'))->format('Y-m-d H:i:s'),
        ]);

        $this->connection->executeStatement('PRAGMA foreign_keys = ON');

        static::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        static::ensureKernelShutdown();
        parent::tearDown();
    }

    private function loginAsAdmin(): void
    {
        static::bootKernel();
        $provider = static::getContainer()->get(\App\Security\User\AppUserProvider::class);
        $user = $provider->loadUserByIdentifier('admin@example.com');
        static::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->client->loginUser($user);
    }

    public function testAuditLogListing(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/security/audit');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Security audit log', $this->client->getResponse()->getContent());
        self::assertGreaterThan(0, $crawler->filter('table tbody tr')->count());
        self::assertStringContainsString('user.role.assigned', $this->client->getResponse()->getContent());
        self::assertStringContainsString('admin@example.com', $this->client->getResponse()->getContent());
    }

    public function testAuditLogFilteringByAction(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/security/audit?action=system.cache.cleared');

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('table tbody tr');
        self::assertSame(1, $rows->count());
        self::assertStringContainsString('system.cache.cleared', $rows->first()->html());
    }
}
