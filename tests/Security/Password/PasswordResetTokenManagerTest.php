<?php

declare(strict_types=1);

namespace App\Tests\Security\Password;

use App\Security\Password\PasswordResetTokenManager;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenManagerTest extends TestCase
{
    private PasswordResetTokenManager $manager;
    private \Doctrine\DBAL\Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE app_password_reset_token (
                id CHAR(26) PRIMARY KEY,
                user_id CHAR(26) NOT NULL,
                selector VARCHAR(24) NOT NULL,
                verifier_hash VARCHAR(128) NOT NULL,
                requested_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                consumed_at DATETIME DEFAULT NULL,
                metadata TEXT NOT NULL DEFAULT '{}'
            )
        SQL);

        $this->manager = new PasswordResetTokenManager($this->connection, 3600);
    }

    public function testCreateAndValidateToken(): void
    {
        $token = $this->manager->create('01HXUSERTESTACCOUNT0000000', ['initiator' => 'admin@example.com']);

        self::assertSame('01HXUSERTESTACCOUNT0000000', $token->userId);
        self::assertNotEmpty($token->selector);
        self::assertNotEmpty($token->verifier);
        self::assertFalse($token->isExpired());
        self::assertSame(['initiator' => 'admin@example.com'], $token->metadata);

        $validated = $this->manager->validate($token->selector, $token->verifier);
        self::assertNotNull($validated);
        self::assertSame($token->id, $validated->id);
    }

    public function testValidateReturnsNullForWrongVerifier(): void
    {
        $token = $this->manager->create('01HXUSERTESTACCOUNT0000000');
        self::assertNull($this->manager->validate($token->selector, 'wrong-verifier'));
    }

    public function testConsumeMarksTokenConsumed(): void
    {
        $token = $this->manager->create('01HXUSERTESTACCOUNT0000000');
        $this->manager->consume($token->selector);

        $validated = $this->manager->validate($token->selector, $token->verifier);
        self::assertNotNull($validated);
        self::assertTrue($validated->isConsumed());
    }

    public function testPurgeExpiredRemovesTokens(): void
    {
        $oldToken = $this->manager->create('01HXUSERTESTACCOUNT0000000');

        $yesterday = (new \DateTimeImmutable('-2 hours'))->format('Y-m-d H:i:s');
        $this->manager->consume($oldToken->selector);
        $this->connection->update('app_password_reset_token', [
            'expires_at' => $yesterday,
        ], [
            'selector' => $oldToken->selector,
        ]);

        $deleted = $this->manager->purgeExpired(new \DateTimeImmutable());
        self::assertSame(1, $deleted);
    }
}
