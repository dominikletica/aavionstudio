<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class ProjectCapabilityProbeControllerTest extends WebTestCase
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

        foreach (['app_project_user', 'app_role_capability', 'app_project', 'app_user_role', 'app_role', 'app_user'] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $this->connection->executeStatement('CREATE TABLE app_user (id CHAR(26) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(190) NOT NULL, locale VARCHAR(12) NOT NULL, timezone VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT "active", flags TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_role (name VARCHAR(64) PRIMARY KEY, label VARCHAR(190) NOT NULL, is_system INTEGER NOT NULL DEFAULT 1, metadata TEXT NOT NULL DEFAULT "{}")');
        $this->connection->executeStatement('CREATE TABLE app_user_role (user_id CHAR(26) NOT NULL, role_name VARCHAR(64) NOT NULL, assigned_at DATETIME NOT NULL, assigned_by CHAR(26), PRIMARY KEY (user_id, role_name))');
        $this->connection->executeStatement('CREATE TABLE app_role_capability (role_name VARCHAR(64) NOT NULL, capability VARCHAR(190) NOT NULL, PRIMARY KEY (role_name, capability))');
        $this->connection->executeStatement('CREATE TABLE app_project (id CHAR(26) PRIMARY KEY, slug VARCHAR(190) NOT NULL, name VARCHAR(190) NOT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_project_user (project_id CHAR(26) NOT NULL, user_id CHAR(26) NOT NULL, role_name VARCHAR(64) NOT NULL, permissions TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, created_by CHAR(26), PRIMARY KEY (project_id, user_id))');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ([
            ['ROLE_VIEWER', 'Viewer'],
            ['ROLE_EDITOR', 'Editor'],
            ['ROLE_ADMIN', 'Administrator'],
        ] as [$roleName, $label]) {
            $this->connection->insert('app_role', [
                'name' => $roleName,
                'label' => $label,
                'is_system' => 1,
                'metadata' => '{}',
            ]);
        }

        $this->connection->insert('app_role_capability', [
            'role_name' => 'ROLE_ADMIN',
            'capability' => 'project.manage',
        ]);

        $this->connection->insert('app_project', [
            'id' => '01HXPROJECTAAA0000000000000',
            'slug' => 'default',
            'name' => 'Default Project',
        ]);

        $this->connection->insert('app_user', [
            'id' => '01HXADMIN00000000000000000',
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
            'user_id' => '01HXADMIN00000000000000000',
            'role_name' => 'ROLE_ADMIN',
            'assigned_at' => $now,
            'assigned_by' => null,
        ]);

        $this->connection->insert('app_user', [
            'id' => '01HXMEMBER0000000000000000',
            'email' => 'member@example.com',
            'password_hash' => password_hash('Secret123', PASSWORD_BCRYPT),
            'display_name' => 'Member',
            'locale' => 'en',
            'timezone' => 'UTC',
            'status' => 'active',
            'flags' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
            'last_login_at' => null,
        ]);

        $this->connection->insert('app_project_user', [
            'project_id' => '01HXPROJECTAAA0000000000000',
            'user_id' => '01HXMEMBER0000000000000000',
            'role_name' => 'ROLE_VIEWER',
            'permissions' => json_encode(['capabilities' => ['project.manage']], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'created_by' => '01HXADMIN00000000000000000',
        ]);

        $this->connection->insert('app_user', [
            'id' => '01HXOUTSIDER00000000000000',
            'email' => 'outsider@example.com',
            'password_hash' => password_hash('Secret123', PASSWORD_BCRYPT),
            'display_name' => 'Outsider',
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

    public function testAdminWithGlobalCapabilityCanAccess(): void
    {
        $client = static::createClient();
        $userProvider = static::getContainer()->get(\App\Security\User\AppUserProvider::class);
        $admin = $userProvider->loadUserByIdentifier('admin@example.com');
        $client->loginUser($admin);

        $client->request('GET', '/admin/projects/01HXPROJECTAAA0000000000000000/capability/project.manage/probe');
        self::assertResponseIsSuccessful();
    }

    public function testMemberWithProjectCapabilityCanAccess(): void
    {
        $client = static::createClient();
        $userProvider = static::getContainer()->get(\App\Security\User\AppUserProvider::class);
        $member = $userProvider->loadUserByIdentifier('member@example.com');
        $client->loginUser($member);

        $client->request('GET', '/admin/projects/01HXPROJECTAAA0000000000000000/capability/project.manage/probe');
        self::assertResponseIsSuccessful();
    }

    public function testOutsiderDenied(): void
    {
        $client = static::createClient();
        $userProvider = static::getContainer()->get(\App\Security\User\AppUserProvider::class);
        $outsider = $userProvider->loadUserByIdentifier('outsider@example.com');
        $client->loginUser($outsider);

        $client->request('GET', '/admin/projects/01HXPROJECTAAA0000000000000000/capability/project.manage/probe');
        self::assertResponseStatusCodeSame(403);
    }
}
