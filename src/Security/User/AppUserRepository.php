<?php

declare(strict_types=1);

namespace App\Security\User;

use Doctrine\DBAL\Connection;

final class AppUserRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array{ id: string, email: string, display_name: string }|null
     */
    public function findActiveByEmail(string $email): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, email, display_name, status FROM app_user WHERE LOWER(email) = LOWER(:email)',
            ['email' => $email]
        );

        if ($row === false) {
            return null;
        }

        if (($row['status'] ?? 'active') !== 'active') {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'email' => (string) $row['email'],
            'display_name' => (string) $row['display_name'],
        ];
    }

    public function updatePassword(string $userId, string $passwordHash): void
    {
        $this->connection->update(
            'app_user',
            [
                'password_hash' => $passwordHash,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
            [
                'id' => $userId,
            ]
        );
    }

    /**
     * @return array{ id: string, email: string, display_name: string, status: string }|null
     */
    public function findById(string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, email, display_name, status FROM app_user WHERE id = :id',
            ['id' => $id]
        );

        if ($row === false) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'email' => (string) $row['email'],
            'display_name' => (string) $row['display_name'],
            'status' => (string) ($row['status'] ?? 'active'),
        ];
    }
}
