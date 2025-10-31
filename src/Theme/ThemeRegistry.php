<?php

declare(strict_types=1);

namespace App\Theme;

final class ThemeRegistry
{
    /**
     * @var list<ThemeManifest>
     */
    private readonly array $manifests;

    public function __construct(array $manifestsData)
    {
        $this->manifests = array_map(
            static fn (array $manifest): ThemeManifest => ThemeManifest::fromPersistedArray($manifest),
            $manifestsData,
        );
    }

    /**
     * @return list<ThemeManifest>
     */
    public function all(): array
    {
        return $this->manifests;
    }

    public function find(string $slug): ?ThemeManifest
    {
        foreach ($this->manifests as $manifest) {
            if ($manifest->slug === $slug) {
                return $manifest;
            }
        }

        return null;
    }
}
