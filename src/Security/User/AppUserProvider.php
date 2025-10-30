<?php

declare(strict_types=1);

namespace App\Security\User;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class AppUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): AppUser
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                    id,
                    email,
                    password_hash,
                    display_name,
                    locale,
                    timezone,
                    status,
                    flags,
                    last_login_at
                FROM app_user
                WHERE LOWER(email) = LOWER(:email)
            SQL,
            [
                'email' => $identifier,
            ]
        );

        if ($row === false) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        $status = (string) ($row['status'] ?? 'active');

        if ($status === 'disabled') {
            throw new CustomUserMessageAccountStatusException('Your account has been disabled.');
        }

        if ($status === 'pending') {
            throw new CustomUserMessageAccountStatusException('Your account is awaiting activation.');
        }

        if ($status === 'archived') {
            throw new CustomUserMessageAccountStatusException('Your account has been archived.');
        }

        $roles = $this->fetchRoles((string) $row['id']);

        return $this->hydrateUser($row, $roles);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof AppUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === AppUser::class || is_subclass_of($class, AppUser::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$this->supportsClass($user::class)) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" cannot be upgraded.', $user::class));
        }

        $this->connection->executeStatement(
            'UPDATE app_user SET password_hash = :password, updated_at = :updated_at WHERE LOWER(email) = LOWER(:email)',
            [
                'password' => $newHashedPassword,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'email' => $user->getUserIdentifier(),
            ]
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $roles
     */
    private function hydrateUser(array $row, array $roles): AppUser
    {
        $flags = [];
        $rawFlags = (string) ($row['flags'] ?? '{}');

        try {
            $decoded = json_decode($rawFlags, true, flags: JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $flags = $decoded;
            }
        } catch (\JsonException) {
            // ignore malformed flag payloads; treat as empty
        }

        $lastLogin = null;

        if (!empty($row['last_login_at'])) {
            try {
                $lastLogin = new \DateTimeImmutable((string) $row['last_login_at']);
            } catch (\Exception) {
                $lastLogin = null;
            }
        }

        if ($roles === []) {
            $roles = ['ROLE_VIEWER'];
        }

        return new AppUser(
            id: (string) $row['id'],
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            roles: $roles,
            displayName: (string) $row['display_name'],
            locale: (string) $row['locale'],
            timezone: (string) $row['timezone'],
            status: (string) ($row['status'] ?? 'active'),
            lastLoginAt: $lastLogin,
            flags: $flags,
        );
    }

    /**
     * @return list<string>
     */
    private function fetchRoles(string $userId): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT role_name FROM app_user_role WHERE user_id = :id',
            [
                'id' => $userId,
            ]
        );

        return array_map(static fn ($role): string => (string) $role, $rows);
    }
}
