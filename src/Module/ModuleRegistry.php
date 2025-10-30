<?php

declare(strict_types=1);

namespace App\Module;

final class ModuleRegistry
{
    /**
     * @var list<ModuleManifest>
     */
    private readonly array $manifests;

    public function __construct(
        array $manifestsData,
    ) {
        $this->manifests = array_map(
            static fn (array $manifest): ModuleManifest => ModuleManifest::fromPersistedArray($manifest),
            $manifestsData,
        );
    }

    /**
     * @return list<ModuleManifest>
     */
    public function all(): array
    {
        return $this->manifests;
    }

    public function find(string $slug): ?ModuleManifest
    {
        foreach ($this->manifests as $manifest) {
            if ($manifest->slug === $slug) {
                return $manifest;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function enabled(): array
    {
        return array_values(array_filter(
            $this->manifests,
            static fn (ModuleManifest $manifest): bool => $manifest->enabled,
        ));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function capabilities(): array
    {
        $capabilities = [];

        foreach ($this->enabled() as $manifest) {
            foreach ($manifest->capabilities as $capability) {
                $key = (string) ($capability['key'] ?? null);

                if ($key === '') {
                    continue;
                }

                $capabilities[$key] = [
                    'module' => $manifest->slug,
                    'label' => $capability['label'] ?? $key,
                    'default_roles' => $capability['default_roles'] ?? [],
                ];
            }
        }

        return $capabilities;
    }
}
