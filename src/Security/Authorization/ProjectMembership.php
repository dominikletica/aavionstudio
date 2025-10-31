<?php

declare(strict_types=1);

namespace App\Security\Authorization;

final class ProjectMembership
{
    /**
     * @param array<string, mixed> $permissions
     */
    public function __construct(
        public readonly string $projectId,
        public readonly string $userId,
        public readonly string $roleName,
        public readonly array $permissions,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?string $createdBy,
    ) {
    }
}
