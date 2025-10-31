<?php

declare(strict_types=1);

namespace App\Security\Password;

final class PasswordResetToken
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $selector,
        public readonly string $verifier,
        public readonly \DateTimeImmutable $requestedAt,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly ?\DateTimeImmutable $consumedAt,
        public readonly array $metadata = [],
    ) {
    }

    public function isExpired(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $now >= $this->expiresAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }
}
