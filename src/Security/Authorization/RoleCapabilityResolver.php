<?php

declare(strict_types=1);

namespace App\Security\Authorization;

use Doctrine\DBAL\Connection;

final class RoleCapabilityResolver
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function roleHasCapability(string $roleName, string $capability): bool
    {
        $value = $this->connection->fetchOne(
            'SELECT 1 FROM app_role_capability WHERE role_name = :role AND capability = :capability',
            [
                'role' => $roleName,
                'capability' => $capability,
            ]
        );

        return $value !== false;
    }

    /**
     * @param list<string> $roles
     */
    public function anyRoleHasCapability(array $roles, string $capability): bool
    {
        if ($roles === []) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $value = $this->connection->fetchOne(
            sprintf('SELECT 1 FROM app_role_capability WHERE capability = ? AND role_name IN (%s)', $placeholders),
            array_merge([$capability], $roles)
        );

        return $value !== false;
    }
}
