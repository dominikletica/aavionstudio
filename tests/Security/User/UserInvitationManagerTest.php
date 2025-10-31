<?php

declare(strict_types=1);

namespace App\Tests\Security\User;

use App\Security\Audit\SecurityAuditLogger;
use App\Security\User\UserInvitationManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class UserInvitationManagerTest extends TestCase
{
    private Connection $connection;
    private UserInvitationManager $manager;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement('CREATE TABLE app_user_invitation (id CHAR(26) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, token_hash VARCHAR(128) NOT NULL, status VARCHAR(16) NOT NULL, invited_by CHAR(26) DEFAULT NULL, metadata TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_audit_log (id CHAR(26) PRIMARY KEY, actor_id CHAR(26), action VARCHAR(128) NOT NULL, subject_id CHAR(26), context TEXT NOT NULL, ip_hash VARCHAR(128), occurred_at DATETIME NOT NULL)');

        $auditLogger = new SecurityAuditLogger($this->connection);
        $this->manager = new UserInvitationManager($this->connection, $auditLogger, 3600);
    }

    public function testCreateInvitationPersistsRecord(): void
    {
        $invitation = $this->manager->create('Invite@example.com', '01HXINVITER00000000000000', ['role' => 'viewer']);

        self::assertSame('invite@example.com', $invitation->email);
        self::assertTrue($invitation->isPending());
        self::assertFalse($invitation->isExpired(new \DateTimeImmutable('+3599 seconds')));

        $row = $this->connection->fetchAssociative('SELECT * FROM app_user_invitation WHERE email = ?', ['invite@example.com']);
        self::assertNotFalse($row);
    }

    public function testAcceptMarksInvitation(): void
    {
        $invitation = $this->manager->create('accept@example.com');
        $accepted = $this->manager->accept($invitation->token);

        self::assertNotNull($accepted);
        self::assertSame('accepted', $accepted->status);

        $status = $this->connection->fetchOne('SELECT status FROM app_user_invitation WHERE id = ?', [$invitation->id]);
        self::assertSame('accepted', $status);
    }

    public function testPurgeExpiredRemovesPending(): void
    {
        $invitation = $this->manager->create('purge@example.com');

        $this->connection->update('app_user_invitation', [
            'expires_at' => (new \DateTimeImmutable('-2 hours'))->format('Y-m-d H:i:s'),
        ], [
            'id' => $invitation->id,
        ]);

        $deleted = $this->manager->purgeExpired(new \DateTimeImmutable());
        self::assertSame(1, $deleted);
    }

    public function testListReturnsInvitations(): void
    {
        $this->manager->create('list@example.com');
        $collection = $this->manager->list();

        self::assertNotEmpty($collection);
        self::assertSame('list@example.com', $collection[0]->email);
    }
}
