<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminApiKeyControllerTest extends WebTestCase
{
    private Connection $connection;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $this->connection = $container->get(Connection::class);

        $this->connection->executeStatement('PRAGMA foreign_keys = OFF');

        foreach (['app_api_key', 'app_audit_log', 'app_user_role', 'app_role', 'app_user'] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $this->connection->executeStatement('CREATE TABLE app_user (id CHAR(26) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(190) NOT NULL, locale VARCHAR(12) NOT NULL, timezone VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT "active", flags TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_role (name VARCHAR(64) PRIMARY KEY, label VARCHAR(190) NOT NULL, is_system INTEGER NOT NULL DEFAULT 1, metadata TEXT NOT NULL DEFAULT "{}")');
        $this->connection->executeStatement('CREATE TABLE app_user_role (user_id CHAR(26) NOT NULL, role_name VARCHAR(64) NOT NULL, assigned_at DATETIME NOT NULL, assigned_by CHAR(26), PRIMARY KEY (user_id, role_name))');
        $this->connection->executeStatement('CREATE TABLE app_api_key (id CHAR(26) PRIMARY KEY, user_id CHAR(26) NOT NULL, label VARCHAR(190) NOT NULL, hashed_key VARCHAR(128) NOT NULL, scopes TEXT NOT NULL, last_used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_audit_log (id CHAR(26) PRIMARY KEY, actor_id CHAR(26), action VARCHAR(128) NOT NULL, subject_id CHAR(26), context TEXT NOT NULL, ip_hash VARCHAR(128), occurred_at DATETIME NOT NULL)');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->insert('app_role', [
            'name' => 'ROLE_ADMIN',
            'label' => 'Administrator',
            'is_system' => 1,
            'metadata' => '{}',
        ]);

        $this->connection->insert('app_user', [
            'id' => '01HXADMINAPIKEY00000000000',
            'email' => 'admin@example.com',
            'password_hash' => password_hash('Secret123', PASSWORD_BCRYPT),
            'display_name' => 'Admin',
            'locale' => 'en',
            'timezone' => 'UTC',
            'status' => 'active',
            'flags' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
            'last_login_at' => null,
        ]);

        $this->connection->insert('app_user_role', [
            'user_id' => '01HXADMINAPIKEY00000000000',
            'role_name' => 'ROLE_ADMIN',
            'assigned_at' => $now,
            'assigned_by' => null,
        ]);

        $this->connection->insert('app_user', [
            'id' => '01HXAPIUSER000000000000000',
            'email' => 'user@example.com',
            'password_hash' => password_hash('Secret123', PASSWORD_BCRYPT),
            'display_name' => 'API User',
            'locale' => 'en',
            'timezone' => 'UTC',
            'status' => 'active',
            'flags' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
            'last_login_at' => null,
        ]);

        $this->connection->executeStatement('PRAGMA foreign_keys = ON');
        static::ensureKernelShutdown();
    }

    public function testListKeysRequiresUserParameter(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(\App\Security\User\AppUserProvider::class)->loadUserByIdentifier('admin@example.com');
        $client->loginUser($admin);

        $client->request('GET', '/admin/api/api-keys');
        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateAndListApiKeys(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(\App\Security\User\AppUserProvider::class)->loadUserByIdentifier('admin@example.com');
        $client->loginUser($admin);

        $client->request('POST', '/admin/api/api-keys', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'user_id' => '01HXAPIUSER000000000000000',
            'label' => 'Integration key',
            'scopes' => ['content.read', 'content.write'],
            'expires_at' => '2030-01-01T00:00:00+00:00',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('secret', $data);
        self::assertNotEmpty($data['secret']);

        $client->request('GET', '/admin/api/api-keys?user=01HXAPIUSER000000000000000');
        self::assertResponseIsSuccessful();
        $list = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $list);
        self::assertSame('Integration key', $list[0]['label']);

        $auditEntry = $this->connection->fetchAssociative('SELECT action, actor_id FROM app_audit_log ORDER BY occurred_at DESC LIMIT 1');
        self::assertNotFalse($auditEntry);
        self::assertSame('api.key.issued', $auditEntry['action']);
        self::assertSame('01HXADMINAPIKEY00000000000', $auditEntry['actor_id']);
    }

    public function testDeleteRevokesKey(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(\App\Security\User\AppUserProvider::class)->loadUserByIdentifier('admin@example.com');
        $client->loginUser($admin);

        $this->connection->insert('app_api_key', [
            'id' => '01HXAPIKEYTOREVOKE0000000000',
            'user_id' => '01HXAPIUSER000000000000000',
            'label' => 'Temp key',
            'hashed_key' => hash('sha512', 'temp'),
            'scopes' => json_encode(['content.read'], JSON_THROW_ON_ERROR),
            'last_used_at' => null,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'revoked_at' => null,
            'expires_at' => null,
        ]);

        $client->request('DELETE', '/admin/api/api-keys/01HXAPIKEYTOREVOKE0000000000');
        self::assertResponseStatusCodeSame(204);

        $revokedAt = $this->connection->fetchOne('SELECT revoked_at FROM app_api_key WHERE id = ?', ['01HXAPIKEYTOREVOKE0000000000']);
        self::assertNotNull($revokedAt);

        $auditEntry = $this->connection->fetchAssociative('SELECT action, actor_id FROM app_audit_log ORDER BY occurred_at DESC LIMIT 1');
        self::assertNotFalse($auditEntry);
        self::assertSame('api.key.revoked', $auditEntry['action']);
        self::assertSame('01HXADMINAPIKEY00000000000', $auditEntry['actor_id']);
    }
}
