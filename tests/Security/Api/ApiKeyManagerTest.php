<?php

declare(strict_types=1);

namespace App\Tests\Security\Api;

use App\Security\Api\ApiKeyManager;
use App\Security\Audit\SecurityAuditLogger;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class ApiKeyManagerTest extends TestCase
{
    private Connection $connection;
    private ApiKeyManager $manager;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
        $this->connection->executeStatement('PRAGMA foreign_keys = ON');
        $this->connection->executeStatement('CREATE TABLE app_api_key (id CHAR(26) PRIMARY KEY, user_id CHAR(26) NOT NULL, label VARCHAR(190) NOT NULL, hashed_key VARCHAR(128) NOT NULL, scopes TEXT NOT NULL, last_used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_audit_log (id CHAR(26) PRIMARY KEY, actor_id CHAR(26), action VARCHAR(128) NOT NULL, subject_id CHAR(26), context TEXT NOT NULL, ip_hash VARCHAR(128), occurred_at DATETIME NOT NULL)');

        $auditLogger = new SecurityAuditLogger($this->connection);
        $this->manager = new ApiKeyManager($this->connection, $auditLogger);
    }

    public function testIssueAndRevokeApiKey(): void
    {
        $result = $this->manager->issue('01HXUSER000000000000000000', 'Automation key', ['content.read', 'content.write']);

        self::assertArrayHasKey('secret', $result);
        self::assertArrayHasKey('id', $result);
        self::assertNotEmpty($result['secret']);

        $row = $this->connection->fetchAssociative('SELECT * FROM app_api_key WHERE id = ?', [$result['id']]);
        self::assertNotFalse($row);
        self::assertSame('Automation key', $row['label']);
        self::assertSame(hash('sha512', $result['secret']), $row['hashed_key']);

        $listed = $this->manager->listForUser('01HXUSER000000000000000000');
        self::assertCount(1, $listed);
        self::assertTrue($listed[0]->isActive());
        self::assertSame(['content.read', 'content.write'], $listed[0]->scopes);

        $this->manager->revoke($result['id'], '01HXADMINUSER0000000000000');

        $revoked = $this->manager->get($result['id']);
        self::assertNotNull($revoked);
        self::assertNotNull($revoked->revokedAt);

        $auditCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_audit_log');
        self::assertGreaterThanOrEqual(2, $auditCount);
    }
}
