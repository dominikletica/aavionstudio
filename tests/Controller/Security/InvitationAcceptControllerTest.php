<?php

declare(strict_types=1);

namespace App\Tests\Controller\Security;

use App\Security\User\UserInvitationManager;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InvitationAcceptControllerTest extends WebTestCase
{
    private Connection $connection;
    private UserInvitationManager $manager;

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
        foreach (['app_user_role', 'app_role', 'app_user', 'app_user_invitation', 'app_audit_log'] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $this->connection->executeStatement('CREATE TABLE app_user (id CHAR(26) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(190) NOT NULL, locale VARCHAR(12) NOT NULL, timezone VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT "active", flags TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_role (name VARCHAR(64) PRIMARY KEY, label VARCHAR(190) NOT NULL, is_system INTEGER NOT NULL DEFAULT 1, metadata TEXT NOT NULL DEFAULT "{}")');
        $this->connection->executeStatement('CREATE TABLE app_user_role (user_id CHAR(26) NOT NULL, role_name VARCHAR(64) NOT NULL, assigned_at DATETIME NOT NULL, assigned_by CHAR(26), PRIMARY KEY (user_id, role_name))');
        $this->connection->executeStatement('CREATE TABLE app_user_invitation (id CHAR(26) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, token_hash VARCHAR(128) NOT NULL, status VARCHAR(16) NOT NULL, invited_by CHAR(26), metadata TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_audit_log (id CHAR(26) PRIMARY KEY, actor_id CHAR(26), action VARCHAR(128) NOT NULL, subject_id CHAR(26), context TEXT NOT NULL, ip_hash VARCHAR(128), occurred_at DATETIME NOT NULL)');

        $this->manager = $container->get(UserInvitationManager::class);
        $this->connection->executeStatement('PRAGMA foreign_keys = ON');
        static::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        static::ensureKernelShutdown();
        parent::tearDown();
    }

    public function testAcceptInvitationCreatesUser(): void
    {
        $invitation = $this->manager->create('invitee@example.com');

        $client = static::createClient();
        $client->followRedirects(false);
        $client->request('GET', '/invite/'.$invitation->token);
        self::assertResponseIsSuccessful();

        $client->submitForm('Activate account', [
            'invitation_accept[display_name]' => 'Invitee',
            'invitation_accept[plainPassword][first]' => 'StrongPass123',
            'invitation_accept[plainPassword][second]' => 'StrongPass123',
        ]);

        self::assertResponseRedirects('/login');
        $client->followRedirect();

        $row = $this->connection->fetchAssociative('SELECT id, email, password_hash FROM app_user WHERE email = ?', ['invitee@example.com']);
        self::assertNotFalse($row);
        self::assertSame('invitee@example.com', $row['email']);
        self::assertNotEmpty($row['password_hash']);
        self::assertSame(0, strncmp('$', (string) $row['password_hash'], 1));
        self::assertTrue(password_verify('StrongPass123', (string) $row['password_hash']));

        $status = $this->connection->fetchOne('SELECT status FROM app_user_invitation');
        self::assertSame('accepted', $status);

        $roles = $this->connection->fetchFirstColumn('SELECT role_name FROM app_user_role WHERE user_id = ?', [$row['id']]);
        self::assertContains('ROLE_VIEWER', $roles);

        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $client->submitForm('Sign in', [
            'email' => 'invitee@example.com',
            'password' => 'StrongPass123',
        ]);

        self::assertResponseRedirects('/admin');

        $tokenStorage = $client->getContainer()->get('security.token_storage');
        $token = $tokenStorage?->getToken();
        self::assertNotNull($token);
        $user = $token->getUser();
        self::assertInstanceOf(\App\Security\User\AppUser::class, $user);
        self::assertSame('invitee@example.com', $user->getUserIdentifier());
        self::assertContains('ROLE_VIEWER', $user->getRoles());
    }

    public function testInvalidTokenShowsError(): void
    {
        $client = static::createClient();
        $client->request('GET', '/invite/invalid-token');

        self::assertResponseRedirects('/login');
    }
}
