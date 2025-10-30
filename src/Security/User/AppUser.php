<?php

declare(strict_types=1);

namespace App\Security\User;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Immutable value object representing an authenticated user.
 */
final class AppUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param list<string>          $roles
     * @param array<string, mixed>  $flags
     */
    public function __construct(
        private readonly string $id,
        private readonly string $email,
        private readonly string $passwordHash,
        private readonly array $roles,
        private readonly string $displayName,
        private readonly string $locale,
        private readonly string $timezone,
        private readonly string $status,
        private readonly ?\DateTimeImmutable $lastLoginAt,
        private readonly array $flags,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        // Guarantee basic role.
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function eraseCredentials(): void
    {
        // No-op: credentials are not stored beyond the password hash.
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    public function withPassword(string $passwordHash): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            passwordHash: $passwordHash,
            roles: $this->roles,
            displayName: $this->displayName,
            locale: $this->locale,
            timezone: $this->timezone,
            status: $this->status,
            lastLoginAt: $this->lastLoginAt,
            flags: $this->flags,
        );
    }
}
