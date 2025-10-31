<?php

declare(strict_types=1);

namespace App\Security\Capability;

final class CapabilityDescriptor
{
    /**
     * @param list<string>         $defaultRoles
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $key,
        public readonly string $module,
        public readonly string $label,
        public readonly array $defaultRoles,
        public readonly array $metadata = [],
    ) {
    }
}
