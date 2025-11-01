<?php

declare(strict_types=1);

namespace App\Tests\Controller\Security;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase
{
    private Connection $connection;

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

        foreach (['app_user_role', 'app_role', 'app_user'] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $this->connection->executeStatement('CREATE TABLE app_user (id CHAR(26) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(190) NOT NULL, locale VARCHAR(12) NOT NULL, timezone VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT "active", flags TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_role (name VARCHAR(64) PRIMARY KEY, label VARCHAR(190) NOT NULL, is_system INTEGER NOT NULL DEFAULT 1, metadata TEXT NOT NULL DEFAULT "{}")');
        $this->connection->executeStatement('CREATE TABLE app_user_role (user_id CHAR(26) NOT NULL, role_name VARCHAR(64) NOT NULL, assigned_at DATETIME NOT NULL, assigned_by CHAR(26), PRIMARY KEY (user_id, role_name))');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->insert('app_role', [
            'name' => 'ROLE_ADMIN',
            'label' => 'Administrator',
            'is_system' => 1,
            'metadata' => '{}',
        ]);

        $this->connection->insert('app_user', [
            'id' => '01HXLOGINUSER0000000000000',
            'email' => 'login@example.com',
            'password_hash' => password_hash('Secret123', PASSWORD_BCRYPT),
            'display_name' => 'Login User',
            'locale' => 'en',
            'timezone' => 'UTC',
            'status' => 'active',
            'flags' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
            'last_login_at' => null,
        ]);

        $this->connection->insert('app_user_role', [
            'user_id' => '01HXLOGINUSER0000000000000',
            'role_name' => 'ROLE_ADMIN',
            'assigned_at' => $now,
            'assigned_by' => null,
        ]);

        $this->connection->executeStatement('PRAGMA foreign_keys = ON');
        static::ensureKernelShutdown();
    }

    public function testLoginSuccessRedirectsToAdmin(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Sign in')->form([
            'email' => 'login@example.com',
            'password' => 'Secret123',
        ]);

        $client->submit($form);
        self::assertResponseRedirects('/admin');
        $client->followRedirect();

        self::assertSame('/admin', $client->getRequest()->getPathInfo());
    }

    public function testLoginFailureShowsErrorMessage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Sign in')->form([
            'email' => 'login@example.com',
            'password' => 'WrongPassword',
        ]);

        $client->submit($form);
        self::assertResponseRedirects('/login');
        $crawler = $client->followRedirect();

        self::assertGreaterThan(0, $crawler->filter('.alert.alert--danger')->count());
    }
}
