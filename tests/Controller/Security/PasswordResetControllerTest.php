<?php

declare(strict_types=1);

namespace App\Tests\Controller\Security;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PasswordResetControllerTest extends WebTestCase
{
    private Connection $connection;
    private \App\Security\Password\PasswordResetTokenManager $tokenManager;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        static::bootKernel();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->tokenManager = static::getContainer()->get(\App\Security\Password\PasswordResetTokenManager::class);

        $this->connection->executeStatement('DROP TABLE IF EXISTS app_user');
        $this->connection->executeStatement('DROP TABLE IF EXISTS app_password_reset_token');
        $this->connection->executeStatement('DROP TABLE IF EXISTS app_audit_log');

        $this->connection->executeStatement('CREATE TABLE app_user (id CHAR(26) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(190) NOT NULL, locale VARCHAR(12) NOT NULL, timezone VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT "active", flags TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_password_reset_token (id CHAR(26) PRIMARY KEY, user_id CHAR(26) NOT NULL, selector VARCHAR(24) NOT NULL, verifier_hash VARCHAR(128) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, consumed_at DATETIME DEFAULT NULL, metadata TEXT NOT NULL DEFAULT "{}")');
        $this->connection->executeStatement('CREATE TABLE app_audit_log (id CHAR(26) PRIMARY KEY, actor_id CHAR(26), action VARCHAR(128) NOT NULL, subject_id CHAR(26), context TEXT NOT NULL, ip_hash VARCHAR(128), occurred_at DATETIME NOT NULL)');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->insert('app_user', [
            'id' => '01HXRESETUSER00000000000000',
            'email' => 'reset@example.com',
            'password_hash' => password_hash('initialPass123', PASSWORD_BCRYPT),
            'display_name' => 'Reset User',
            'locale' => 'en',
            'timezone' => 'UTC',
            'status' => 'active',
            'flags' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
            'last_login_at' => $now,
        ]);

        static::ensureKernelShutdown();
    }

    public function testRequestCreatesTokenRecord(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/password/forgot');
        self::assertResponseIsSuccessful();

        $client->submitForm('Send reset link', [
            'password_reset_request[email]' => 'reset@example.com',
        ]);

        self::assertResponseRedirects('/login');
        $client->followRedirect();

        $token = $this->connection->fetchAssociative('SELECT * FROM app_password_reset_token');
        self::assertNotFalse($token);
    }

    public function testResetUpdatesPasswordAndConsumesToken(): void
    {
        $token = $this->tokenManager->create('01HXRESETUSER00000000000000', ['email' => 'reset@example.com']);

        $client = static::createClient();
        $selector = $token->selector;
        $verifier = $token->verifier;
        $crawler = $client->request('GET', '/password/reset/'.$selector.'?token='.$verifier);
        self::assertResponseIsSuccessful();

        $client->submitForm('Update password', [
            'password_reset[plainPassword][first]' => 'newSecret123',
            'password_reset[plainPassword][second]' => 'newSecret123',
        ]);

        self::assertResponseRedirects('/login');

        $hash = $this->connection->fetchOne('SELECT password_hash FROM app_user WHERE email = ?', ['reset@example.com']);
        self::assertTrue(password_verify('newSecret123', (string) $hash));

        $consumed = $this->connection->fetchOne('SELECT consumed_at FROM app_password_reset_token WHERE selector = ?', [$selector]);
        self::assertNotNull($consumed);
    }
}
