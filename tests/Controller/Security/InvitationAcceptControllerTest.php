<?php

declare(strict_types=1);

namespace App\Tests\Controller\Security;

use App\Security\User\UserInvitationManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
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

        foreach (['app_user_invitation', 'app_audit_log'] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $this->connection->executeStatement('CREATE TABLE app_user_invitation (id CHAR(26) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, token_hash VARCHAR(128) NOT NULL, status VARCHAR(16) NOT NULL, invited_by CHAR(26), metadata TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_audit_log (id CHAR(26) PRIMARY KEY, actor_id CHAR(26), action VARCHAR(128) NOT NULL, subject_id CHAR(26), context TEXT NOT NULL, ip_hash VARCHAR(128), occurred_at DATETIME NOT NULL)');

        $this->manager = $container->get(UserInvitationManager::class);
        static::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        static::ensureKernelShutdown();
        parent::tearDown();
    }

    public function testAcceptInvitationRedirectsToLogin(): void
    {
        $invitation = $this->manager->create('invitee@example.com');

        $client = static::createClient();
        $client->request('GET', '/invite/'.$invitation->token);

        self::assertResponseRedirects('/login');
    }

    public function testInvalidTokenShowsError(): void
    {
        $client = static::createClient();
        $client->request('GET', '/invite/invalid-token');

        self::assertResponseRedirects('/login');
    }
}
