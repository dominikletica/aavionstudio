<?php

declare(strict_types=1);

namespace App\Security\Api;

/**
 * Read-only representation of an API key row.
 */
final class ApiKey
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $label,
        public readonly array $scopes,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $lastUsedAt,
        public readonly ?\DateTimeImmutable $expiresAt,
        public readonly ?\DateTimeImmutable $revokedAt,
    ) {
    }

    public function isActive(): bool
    {
        if ($this->revokedAt !== null) {
            return false;
        }

        if ($this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable()) {
            return false;
        }

        return true;
    }
}
