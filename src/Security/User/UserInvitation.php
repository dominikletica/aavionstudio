<?php

declare(strict_types=1);

namespace App\Security\User;

final class UserInvitation
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $token,
        public readonly string $status,
        public readonly string $invitedBy,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly ?\DateTimeImmutable $acceptedAt,
        public readonly ?\DateTimeImmutable $cancelledAt,
        public readonly array $metadata = [],
    ) {
    }

    public function isExpired(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $now >= $this->expiresAt;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired() && $this->acceptedAt === null && $this->cancelledAt === null;
    }
}
