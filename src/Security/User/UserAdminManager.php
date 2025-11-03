<?php

declare(strict_types=1);

namespace App\Security\User;

use App\Security\Audit\SecurityAuditLogger;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Provides admin tooling for listing and updating users.
 */
final class UserAdminManager
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SecurityAuditLogger $auditLogger,
    ) {
    }

    /**
     * @return list<array{
     *     id: string,
     *     email: string,
     *     display_name: string,
     *     status: string,
     *     locale: string,
     *     timezone: string,
     *     created_at: ?\DateTimeImmutable,
     *     last_login_at: ?\DateTimeImmutable,
     *     roles: list<string>
     * }>
     */
    public function listUsers(?string $query = null, ?string $status = null, int $limit = 100, int $offset = 0): array
    {
        $conditions = [];
        $parameters = [];
        if ($query !== null && $query !== '') {
            $conditions[] = '(LOWER(u.email) LIKE :query OR LOWER(u.display_name) LIKE :query)';
            $parameters['query'] = '%'.mb_strtolower($query).'%';
        }

        if ($status !== null && $status !== '') {
            $conditions[] = 'u.status = :status';
            $parameters['status'] = $status;
        }

        $sql = <<<'SQL'
            SELECT
                u.id,
                u.email,
                u.display_name,
                u.locale,
                u.timezone,
                u.status,
                u.created_at,
                u.last_login_at
            FROM app_user u
        SQL;

        if ($conditions !== []) {
            $sql .= ' WHERE '.implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY u.created_at DESC, u.email ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->connection->prepare($sql);

        foreach ($parameters as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue('limit', $limit, ParameterType::INTEGER);
        $stmt->bindValue('offset', $offset, ParameterType::INTEGER);

        $result = $stmt->executeQuery();

        $users = [];
        $userIds = [];

        while ($row = $result->fetchAssociative()) {
            $id = (string) $row['id'];
            $users[$id] = [
                'id' => $id,
                'email' => (string) $row['email'],
                'display_name' => (string) $row['display_name'],
                'locale' => (string) ($row['locale'] ?? 'en'),
                'timezone' => (string) ($row['timezone'] ?? 'UTC'),
                'status' => (string) ($row['status'] ?? 'active'),
                'created_at' => $this->parseDateTime($row['created_at'] ?? null),
                'last_login_at' => $this->parseDateTime($row['last_login_at'] ?? null),
                'roles' => [],
            ];
            $userIds[] = $id;
        }

        if ($userIds !== []) {
            $roles = $this->fetchRolesForUsers($userIds);

            foreach ($roles as $userId => $roleNames) {
                if (isset($users[$userId])) {
                    $users[$userId]['roles'] = $roleNames;
                }
            }
        }

        return array_values($users);
    }

    /**
     * @return array{
     *     id: string,
     *     email: string,
     *     display_name: string,
     *     locale: string,
     *     timezone: string,
     *     status: string,
     *     created_at: ?\DateTimeImmutable,
     *     updated_at: ?\DateTimeImmutable,
     *     last_login_at: ?\DateTimeImmutable,
     *     roles: list<string>
     *     flags: array<string, mixed>
     * }|null
     */
    public function getUser(string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT id, email, display_name, locale, timezone, status, created_at, updated_at, last_login_at, flags
                FROM app_user
                WHERE id = :id
            SQL,
            ['id' => $id]
        );

        if ($row === false) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'email' => (string) $row['email'],
            'display_name' => (string) $row['display_name'],
            'locale' => (string) ($row['locale'] ?? 'en'),
            'timezone' => (string) ($row['timezone'] ?? 'UTC'),
            'status' => (string) ($row['status'] ?? 'active'),
            'created_at' => $this->parseDateTime($row['created_at'] ?? null),
            'updated_at' => $this->parseDateTime($row['updated_at'] ?? null),
            'last_login_at' => $this->parseDateTime($row['last_login_at'] ?? null),
            'roles' => $this->fetchRolesForUser($id),
            'flags' => $this->decodeFlags($row['flags'] ?? '{}'),
        ];
    }

    /**
     * @return array<string,string>
     */
    public function getRoleChoices(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT name, label FROM app_role ORDER BY label ASC'
        );

        $choices = [];

        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $label = (string) ($row['label'] ?: $name);
            $choices[$label] = $name;
        }

        if (!in_array('ROLE_VIEWER', array_values($choices), true)) {
            $choices['Viewer'] = 'ROLE_VIEWER';
        }

        ksort($choices);

        return $choices;
    }

    /**
     * @param array{display_name: string, locale: string, timezone: string, status: string, flags?: array<string, mixed>} $profile
     * @param list<string> $roles
     */
    public function updateUser(string $id, array $profile, array $roles, ?string $actorId = null): void
    {
        $roles = array_values(array_unique($roles));
        sort($roles);

        if (!in_array('ROLE_VIEWER', $roles, true)) {
            $roles[] = 'ROLE_VIEWER';
        }

        $this->connection->transactional(function (Connection $connection) use ($id, $profile, $roles, $actorId): void {
            $existing = $this->getUser($id);

            if ($existing === null) {
                throw new \RuntimeException(sprintf('User "%s" not found.', $id));
            }

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            $flags = \is_array($profile['flags'] ?? null) ? $profile['flags'] : $existing['flags'] ?? [];

            $connection->update('app_user', [
                'display_name' => $profile['display_name'],
                'locale' => $profile['locale'],
                'timezone' => $profile['timezone'],
                'status' => $profile['status'],
                'flags' => json_encode($flags, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ], [
                'id' => $id,
            ]);

            $currentRoles = $this->fetchRolesForUser($id);
            sort($currentRoles);

            if ($currentRoles !== $roles) {
                $connection->executeStatement('DELETE FROM app_user_role WHERE user_id = :id', ['id' => $id]);

                foreach ($roles as $role) {
                    $connection->insert('app_user_role', [
                        'user_id' => $id,
                        'role_name' => $role,
                        'assigned_at' => $now,
                        'assigned_by' => $actorId,
                    ]);
                }

                $this->auditLogger->log('user.roles.updated', [
                    'user_id' => $id,
                    'added' => array_values(array_diff($roles, $currentRoles)),
                    'removed' => array_values(array_diff($currentRoles, $roles)),
                ], actorId: $actorId, subjectId: $id);
            }

            $changes = [];

            foreach ($profile as $key => $value) {
                if (!array_key_exists($key, $existing)) {
                    continue;
                }

                if ($existing[$key] !== $value) {
                    $changes[$key] = [
                        'old' => $existing[$key],
                        'new' => $value,
                    ];
                }
            }

            if (($existing['flags'] ?? []) !== $flags) {
                $changes['flags'] = [
                    'old' => $existing['flags'] ?? [],
                    'new' => $flags,
                ];
            }

            if ($changes !== []) {
                $this->auditLogger->log('user.profile.updated', [
                    'user_id' => $id,
                    'changes' => $changes,
                ], actorId: $actorId, subjectId: $id);
            }
        });
    }

    /**
     * @param list<string> $userIds
     * @return array<string, list<string>>
     */
    private function fetchRolesForUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT user_id, role_name FROM app_user_role WHERE user_id IN (%s)', $placeholders),
            $userIds
        );

        $result = [];

        foreach ($rows as $row) {
            $userId = (string) $row['user_id'];
            $roleName = (string) $row['role_name'];
            $result[$userId] ??= [];
            $result[$userId][] = $roleName;
        }

        foreach ($result as &$userRoles) {
            $userRoles = array_values(array_unique($userRoles));
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function fetchRolesForUser(string $userId): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT role_name FROM app_user_role WHERE user_id = :id',
            ['id' => $userId]
        );

        return array_values(array_map(static fn ($role): string => (string) $role, $rows));
    }

    private function parseDateTime(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeFlags(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        try {
            $flags = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($flags) ? $flags : [];
    }
}
