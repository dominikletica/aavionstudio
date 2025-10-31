<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Security\User\UserInvitationManager;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class UserInvitationControllerTest extends WebTestCase
{
    use MailerAssertionsTrait;

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

        foreach (['app_user_invitation', 'app_audit_log', 'app_user_role', 'app_role', 'app_user'] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $this->connection->executeStatement('CREATE TABLE app_user (id CHAR(26) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(190) NOT NULL, locale VARCHAR(12) NOT NULL, timezone VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT "active", flags TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_role (name VARCHAR(64) PRIMARY KEY, label VARCHAR(190) NOT NULL, is_system INTEGER NOT NULL DEFAULT 1, metadata TEXT NOT NULL DEFAULT "{}")');
        $this->connection->executeStatement('CREATE TABLE app_user_role (user_id CHAR(26) NOT NULL, role_name VARCHAR(64) NOT NULL, assigned_at DATETIME NOT NULL, assigned_by CHAR(26), PRIMARY KEY (user_id, role_name))');
        $this->connection->executeStatement('CREATE TABLE app_user_invitation (id CHAR(26) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, token_hash VARCHAR(128) NOT NULL, status VARCHAR(16) NOT NULL, invited_by CHAR(26), metadata TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL)');
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

    public function testIndexRendersInvitations(): void
    {
        $this->loginAsAdmin();
        $this->client->request('GET', '/admin/users/invitations');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('User Invitations', $this->client->getResponse()->getContent());
    }

    public function testCreateInvitationStoresAndSendsEmail(): void
    {
        $this->loginAsAdmin();
        $this->client->request('GET', '/admin/users/invitations');
        $this->client->submitForm('Send invitation', [
            'invitation_create[email]' => 'newuser@example.com',
        ]);

        self::assertResponseRedirects('/admin/users/invitations');
        $this->client->followRedirect();

        $row = $this->connection->fetchAssociative('SELECT * FROM app_user_invitation WHERE email = ?', ['newuser@example.com']);
        self::assertNotFalse($row);
    }

    public function testCancelInvitationUpdatesStatus(): void
    {
        static::bootKernel();
        $container = static::getContainer();
        $manager = $container->get(UserInvitationManager::class);
        $invitation = $manager->create('cancel@example.com', '01HXADMINUSER0000000000000');
        static::ensureKernelShutdown();

        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/users/invitations');
        $form = $crawler->filter('form')->reduce(function ($node) use ($invitation) {
            $action = $node->attr('action') ?? '';
            return str_contains($action, $invitation->id);
        })->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/users/invitations');
        $status = $this->connection->fetchOne('SELECT status FROM app_user_invitation WHERE id = ?', [$invitation->id]);
        self::assertSame('cancelled', $status);
    }
}
