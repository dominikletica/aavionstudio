<?php

declare(strict_types=1);

namespace App\Security\Authorization;

final class ProjectCapabilityRequirement
{
    public function __construct(
        public readonly string $capability,
        public readonly string $projectId,
    ) {
    }
}
