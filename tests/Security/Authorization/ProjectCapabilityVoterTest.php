<?php

declare(strict_types=1);

namespace App\Tests\Security\Authorization;

use App\Security\Authorization\ProjectCapabilityRequirement;
use App\Security\Authorization\ProjectCapabilityVoter;
use App\Security\Authorization\ProjectMembershipRepository;
use App\Security\Authorization\RoleCapabilityResolver;
use App\Security\User\AppUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class ProjectCapabilityVoterTest extends TestCase
{
    private Connection $connection;
    private ProjectCapabilityVoter $voter;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement('CREATE TABLE app_role_capability (role_name VARCHAR(64) NOT NULL, capability VARCHAR(190) NOT NULL, PRIMARY KEY (role_name, capability))');
        $this->connection->executeStatement('CREATE TABLE app_project_user (project_id CHAR(26) NOT NULL, user_id CHAR(26) NOT NULL, role_name VARCHAR(64) NOT NULL, permissions TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, created_by CHAR(26), PRIMARY KEY (project_id, user_id))');

        $resolver = new RoleCapabilityResolver($this->connection);
        $repository = new ProjectMembershipRepository($this->connection);
        $this->voter = new ProjectCapabilityVoter($resolver, $repository);
    }

    public function testGlobalRoleGrantsCapability(): void
    {
        $this->connection->insert('app_role_capability', [
            'role_name' => 'ROLE_ADMIN',
            'capability' => 'content.publish',
        ]);

        $user = $this->createUser('01HXUSER000000000000000001', ['ROLE_ADMIN']);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $result = $this->voter->vote($token, new ProjectCapabilityRequirement('content.publish', '01HXPROJECT0000000000000000'), [ProjectCapabilityVoter::ATTRIBUTE]);

        self::assertSame(ProjectCapabilityVoter::ACCESS_GRANTED, $result);
    }

    public function testMembershipRoleGrantsCapability(): void
    {
        $this->connection->insert('app_role_capability', [
            'role_name' => 'ROLE_EDITOR',
            'capability' => 'content.publish',
        ]);

        $this->connection->insert('app_project_user', [
            'project_id' => '01HXPROJECT0000000000000000',
            'user_id' => '01HXUSER000000000000000002',
            'role_name' => 'ROLE_EDITOR',
            'permissions' => '{}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'created_by' => null,
        ]);

        $user = $this->createUser('01HXUSER000000000000000002', ['ROLE_VIEWER']);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $result = $this->voter->vote($token, new ProjectCapabilityRequirement('content.publish', '01HXPROJECT0000000000000000'), [ProjectCapabilityVoter::ATTRIBUTE]);

        self::assertSame(ProjectCapabilityVoter::ACCESS_GRANTED, $result);
    }

    public function testMembershipExplicitPermissionGrantsCapability(): void
    {
        $this->connection->insert('app_project_user', [
            'project_id' => '01HXPROJECT0000000000000000',
            'user_id' => '01HXUSER000000000000000003',
            'role_name' => 'ROLE_VIEWER',
            'permissions' => json_encode(['content.publish' => true], JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'created_by' => null,
        ]);

        $user = $this->createUser('01HXUSER000000000000000003', ['ROLE_VIEWER']);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $result = $this->voter->vote($token, new ProjectCapabilityRequirement('content.publish', '01HXPROJECT0000000000000000'), [ProjectCapabilityVoter::ATTRIBUTE]);

        self::assertSame(ProjectCapabilityVoter::ACCESS_GRANTED, $result);
    }

    public function testAccessDeniedWhenNoCapability(): void
    {
        $user = $this->createUser('01HXUSER000000000000000004', ['ROLE_VIEWER']);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $result = $this->voter->vote($token, new ProjectCapabilityRequirement('content.publish', '01HXPROJECT0000000000000000'), [ProjectCapabilityVoter::ATTRIBUTE]);

        self::assertSame(ProjectCapabilityVoter::ACCESS_DENIED, $result);
    }

    private function createUser(string $id, array $roles): AppUser
    {
        return new AppUser(
            id: $id,
            email: $id.'@example.com',
            passwordHash: '$argon2id$v=19$m=65536,t=4,p=1$MTIzNA$abcdefghijklmnopqrstuv',
            roles: $roles,
            displayName: 'Test User',
            locale: 'en',
            timezone: 'UTC',
            status: 'active',
            lastLoginAt: new \DateTimeImmutable(),
            flags: [],
        );
    }
}
