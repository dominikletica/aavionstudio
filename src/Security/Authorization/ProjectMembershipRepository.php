<?php

declare(strict_types=1);

namespace App\Security\Authorization;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

final class ProjectMembershipRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param array<string, mixed> $permissions
     */
    public function assign(string $projectId, string $userId, string $roleName, array $permissions = [], ?string $createdBy = null): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if (!isset($permissions['capabilities']) || !is_array($permissions['capabilities'])) {
            $permissions['capabilities'] = [];
        }

        $encodedPermissions = json_encode($permissions, JSON_THROW_ON_ERROR);

        $this->connection->executeStatement(
            'INSERT INTO app_project_user (project_id, user_id, role_name, permissions, created_at, created_by)
             VALUES (:project_id, :user_id, :role_name, :permissions, :created_at, :created_by)
             ON CONFLICT(project_id, user_id) DO UPDATE SET role_name = :role_name_u, permissions = :permissions_u, created_by = :created_by_u',
            [
                'project_id' => $projectId,
                'user_id' => $userId,
                'role_name' => $roleName,
                'permissions' => $encodedPermissions,
                'created_at' => $now,
                'created_by' => $createdBy,
                'role_name_u' => $roleName,
                'permissions_u' => $encodedPermissions,
                'created_by_u' => $createdBy,
            ]
        );
    }

    public function revoke(string $projectId, string $userId): void
    {
        $this->connection->delete('app_project_user', [
            'project_id' => $projectId,
            'user_id' => $userId,
        ]);
    }

    public function find(string $projectId, string $userId): ?ProjectMembership
    {
        $row = $this->connection->fetchAssociative(
            'SELECT project_id, user_id, role_name, permissions, created_at, created_by FROM app_project_user WHERE project_id = :project_id AND user_id = :user_id',
            [
                'project_id' => $projectId,
                'user_id' => $userId,
            ]
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @return list<ProjectMembership>
     */
    public function forUser(string $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT project_id, user_id, role_name, permissions, created_at, created_by FROM app_project_user WHERE user_id = :user_id ORDER BY created_at DESC',
            [
                'user_id' => $userId,
            ]
        );

        return array_map(fn (array $row): ProjectMembership => $this->hydrate($row), $rows);
    }

    /**
     * @return list<ProjectMembership>
     */
    public function forProject(string $projectId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT project_id, user_id, role_name, permissions, created_at, created_by FROM app_project_user WHERE project_id = :project_id ORDER BY created_at DESC',
            [
                'project_id' => $projectId,
            ]
        );

        return array_map(fn (array $row): ProjectMembership => $this->hydrate($row), $rows);
    }

    public function userHasRole(string $projectId, string $userId, string $roleName): bool
    {
        $value = $this->connection->fetchOne(
            'SELECT 1 FROM app_project_user WHERE project_id = :project_id AND user_id = :user_id AND role_name = :role_name',
            [
                'project_id' => $projectId,
                'user_id' => $userId,
                'role_name' => $roleName,
            ]
        );

        return $value !== false;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ProjectMembership
    {
        $permissions = [];

        if (!empty($row['permissions'])) {
            $decoded = json_decode((string) $row['permissions'], true);
            if (is_array($decoded)) {
                $permissions = $decoded;
            }
        }

        if (!isset($permissions['capabilities']) || !is_array($permissions['capabilities'])) {
            $permissions['capabilities'] = [];
        }

        return new ProjectMembership(
            projectId: (string) $row['project_id'],
            userId: (string) $row['user_id'],
            roleName: (string) $row['role_name'],
            permissions: $permissions,
            createdAt: new \DateTimeImmutable((string) $row['created_at']),
            createdBy: $row['created_by'] !== null ? (string) $row['created_by'] : null,
        );
    }
}
