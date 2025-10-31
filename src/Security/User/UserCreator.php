<?php

declare(strict_types=1);

namespace App\Security\User;

use App\Security\Audit\SecurityAuditLogger;
use Doctrine\DBAL\Connection;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Ulid;

final class UserCreator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SecurityAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param list<string> $roles
     */
    public function create(string $email, string $displayName, string $plainPassword, array $roles = ['ROLE_VIEWER'], string $locale = 'en', string $timezone = 'UTC', array $flags = [], ?string $createdBy = null): AppUser
    {
        $id = (new Ulid())->toBase32();
        $now = new \DateTimeImmutable();

        $prototypeUser = new AppUser(
            id: $id,
            email: strtolower($email),
            passwordHash: '',
            roles: $roles,
            displayName: $displayName,
            locale: $locale,
            timezone: $timezone,
            status: 'active',
            lastLoginAt: null,
            flags: $flags,
        );

        $passwordHash = $this->passwordHasher->hashPassword($prototypeUser, $plainPassword);

        $this->connection->insert('app_user', [
            'id' => $id,
            'email' => strtolower($email),
            'password_hash' => $passwordHash,
            'display_name' => $displayName,
            'locale' => $locale,
            'timezone' => $timezone,
            'status' => 'active',
            'flags' => json_encode($flags, JSON_THROW_ON_ERROR),
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
            'last_login_at' => null,
        ]);

        foreach ($roles as $role) {
            $this->connection->insert('app_user_role', [
                'user_id' => $id,
                'role_name' => $role,
                'assigned_at' => $now->format('Y-m-d H:i:s'),
                'assigned_by' => $createdBy,
            ]);
        }

        $appUser = new AppUser(
            id: $id,
            email: strtolower($email),
            passwordHash: $passwordHash,
            roles: $roles,
            displayName: $displayName,
            locale: $locale,
            timezone: $timezone,
            status: 'active',
            lastLoginAt: null,
            flags: $flags,
        );

        $this->auditLogger->log('user.created.invitation', [
            'user_id' => $id,
            'email' => strtolower($email),
        ], actorId: $createdBy, subjectId: $id);

        return $appUser;
    }
}
