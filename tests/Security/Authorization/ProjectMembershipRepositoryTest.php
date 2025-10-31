<?php

declare(strict_types=1);

namespace App\Tests\Security\Authorization;

use App\Security\Authorization\ProjectMembershipRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class ProjectMembershipRepositoryTest extends TestCase
{
    private Connection $connection;
    private ProjectMembershipRepository $repository;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement('CREATE TABLE app_project (id CHAR(26) PRIMARY KEY, slug VARCHAR(190) NOT NULL, name VARCHAR(190) NOT NULL, locale VARCHAR(12) NOT NULL, timezone VARCHAR(64) NOT NULL, settings TEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_role (name VARCHAR(64) PRIMARY KEY, label VARCHAR(190) NOT NULL, is_system INTEGER NOT NULL DEFAULT 1, metadata TEXT NOT NULL DEFAULT "{}")');
        $this->connection->executeStatement('CREATE TABLE app_project_user (project_id CHAR(26) NOT NULL, user_id CHAR(26) NOT NULL, role_name VARCHAR(64) NOT NULL, permissions TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, created_by CHAR(26), PRIMARY KEY (project_id, user_id))');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->insert('app_project', [
            'id' => '01HXPROJECT0000000000000000',
            'slug' => 'default',
            'name' => 'Default Project',
            'locale' => 'en',
            'timezone' => 'UTC',
            'settings' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->connection->insert('app_role', [
            'name' => 'ROLE_EDITOR',
            'label' => 'Editor',
            'is_system' => 1,
            'metadata' => '{}',
        ]);

        $this->repository = new ProjectMembershipRepository($this->connection);
    }

    public function testAssignAndFindMembership(): void
    {
        $this->repository->assign('01HXPROJECT0000000000000000', '01HXUSER000000000000000000', 'ROLE_EDITOR', ['can_publish' => true], '01HXADMINUSER0000000000000');

        $membership = $this->repository->find('01HXPROJECT0000000000000000', '01HXUSER000000000000000000');
        self::assertNotNull($membership);
        self::assertSame('ROLE_EDITOR', $membership->roleName);
        self::assertTrue($membership->permissions['can_publish']);
    }

    public function testUpdateMembershipOverwritesRoleAndPermissions(): void
    {
        $this->repository->assign('01HXPROJECT0000000000000000', '01HXUSER000000000000000000', 'ROLE_EDITOR', ['can_publish' => false]);
        $this->repository->assign('01HXPROJECT0000000000000000', '01HXUSER000000000000000000', 'ROLE_EDITOR', ['can_publish' => true]);

        $membership = $this->repository->find('01HXPROJECT0000000000000000', '01HXUSER000000000000000000');
        self::assertNotNull($membership);
        self::assertTrue($membership->permissions['can_publish']);
    }

    public function testListMembershipsForUser(): void
    {
        $this->repository->assign('01HXPROJECT0000000000000000', '01HXUSER000000000000000000', 'ROLE_EDITOR');

        $memberships = $this->repository->forUser('01HXUSER000000000000000000');
        self::assertCount(1, $memberships);
        self::assertSame('01HXPROJECT0000000000000000', $memberships[0]->projectId);
    }

    public function testRevokeRemovesMembership(): void
    {
        $this->repository->assign('01HXPROJECT0000000000000000', '01HXUSER000000000000000000', 'ROLE_EDITOR');
        $this->repository->revoke('01HXPROJECT0000000000000000', '01HXUSER000000000000000000');

        $membership = $this->repository->find('01HXPROJECT0000000000000000', '01HXUSER000000000000000000');
        self::assertNull($membership);
    }
}
