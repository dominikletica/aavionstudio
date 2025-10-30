<?php

declare(strict_types=1);

namespace App\Security\Capability;

use App\Module\ModuleRegistry;

final class CapabilityRegistry
{
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
    ) {
    }

    /**
     * @return list<CapabilityDescriptor>
     */
    public function all(): array
    {
        $capabilities = [];

        foreach ($this->moduleRegistry->enabled() as $manifest) {
            foreach ($manifest->capabilities as $capability) {
                $key = (string) ($capability['key'] ?? '');

                if ($key === '') {
                    continue;
                }

                $capabilities[] = new CapabilityDescriptor(
                    key: $key,
                    module: $manifest->slug,
                    label: (string) ($capability['label'] ?? $key),
                    defaultRoles: array_values(array_map('strval', $capability['default_roles'] ?? [])),
                    metadata: (array) ($capability['metadata'] ?? []),
                );
            }
        }

        return $capabilities;
    }

    /**
     * @return list<string>
     */
    public function defaultRolesFor(string $capabilityKey): array
    {
        foreach ($this->all() as $descriptor) {
            if ($descriptor->key === $capabilityKey) {
                return $descriptor->defaultRoles;
            }
        }

        return [];
    }
}
