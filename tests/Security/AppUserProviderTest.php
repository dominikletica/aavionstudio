<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\User\AppUser;
use App\Security\User\AppUserProvider;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class AppUserProviderTest extends TestCase
{
    private Connection $connection;
    private AppUserProvider $provider;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement(
            <<<'SQL'
                CREATE TABLE app_user (
                    id CHAR(26) PRIMARY KEY,
                    email VARCHAR(190) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    display_name VARCHAR(190) NOT NULL,
                    locale VARCHAR(12) NOT NULL,
                    timezone VARCHAR(64) NOT NULL,
                    status VARCHAR(16) NOT NULL DEFAULT 'active',
                    flags TEXT NOT NULL DEFAULT '{}',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    last_login_at DATETIME DEFAULT NULL
                );
            SQL
        );

        $this->connection->executeStatement(
            <<<'SQL'
                CREATE TABLE app_user_role (
                    user_id CHAR(26) NOT NULL,
                    role_name VARCHAR(64) NOT NULL,
                    assigned_at DATETIME NOT NULL,
                    assigned_by CHAR(26),
                    PRIMARY KEY (user_id, role_name)
                );
            SQL
        );

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->insert('app_user', [
            'id' => '01HXUSERTESTACCOUNT0000000',
            'email' => 'admin@example.com',
            'password_hash' => '$argon2id$v=19$m=65536,t=4,p=1$MTIzNA$abcdefghijklmnopqrstuv',
            'display_name' => 'Admin User',
            'locale' => 'en',
            'timezone' => 'UTC',
            'status' => 'active',
            'flags' => json_encode(['mfa' => false], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
            'last_login_at' => $now,
        ]);

        $this->connection->insert('app_user_role', [
            'user_id' => '01HXUSERTESTACCOUNT0000000',
            'role_name' => 'ROLE_ADMIN',
            'assigned_at' => $now,
            'assigned_by' => null,
        ]);

        $this->provider = new AppUserProvider($this->connection);
    }

    public function testLoadUserByIdentifierReturnsHydratedUser(): void
    {
        $user = $this->provider->loadUserByIdentifier('admin@example.com');

        self::assertInstanceOf(AppUser::class, $user);
        self::assertSame('01HXUSERTESTACCOUNT0000000', $user->getId());
        self::assertSame('admin@example.com', $user->getUserIdentifier());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertSame('Admin User', $user->getDisplayName());
        self::assertSame('en', $user->getLocale());
        self::assertSame('UTC', $user->getTimezone());
        self::assertSame('active', $user->getStatus());
        self::assertNotNull($user->getLastLoginAt());
        self::assertSame(['mfa' => false], $user->getFlags());
    }

    public function testLoadUserByIdentifierRejectsUnknownEmail(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->provider->loadUserByIdentifier('missing@example.com');
    }

    public function testDisabledStatusThrowsException(): void
    {
        $this->connection->update('app_user', ['status' => 'disabled'], ['email' => 'admin@example.com']);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Your account has been disabled.');

        $this->provider->loadUserByIdentifier('admin@example.com');
    }

    public function testRefreshUserReloadsLatestData(): void
    {
        $user = $this->provider->loadUserByIdentifier('admin@example.com');

        $this->connection->update('app_user', [
            'display_name' => 'Updated Name',
            'status' => 'pending',
        ], [
            'email' => 'admin@example.com',
        ]);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->provider->refreshUser($user);
    }
}
