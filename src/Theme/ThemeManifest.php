<?php

declare(strict_types=1);

namespace App\Theme;

final class ThemeManifest
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly string $basePath,
        public readonly ?string $version = null,
        public readonly int $priority = 0,
        public readonly ?string $services = null,
        public readonly ?string $assets = 'assets',
        public readonly ?string $repository = null,
        public readonly array $metadata = [],
        public readonly bool $enabled = true,
        public readonly bool $active = false,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $basePath): self
    {
        return new self(
            slug: (string) ($data['slug'] ?? throw new \InvalidArgumentException('Theme slug missing.')),
            name: (string) ($data['name'] ?? throw new \InvalidArgumentException('Theme name missing.')),
            description: (string) ($data['description'] ?? throw new \InvalidArgumentException('Theme description missing.')),
            basePath: $basePath,
            version: isset($data['version']) ? (string) $data['version'] : null,
            priority: (int) ($data['priority'] ?? 0),
            services: isset($data['services']) ? (string) $data['services'] : null,
            assets: isset($data['assets']) ? (string) $data['assets'] : 'assets',
            repository: isset($data['repository']) ? (string) $data['repository'] : null,
            metadata: isset($data['metadata']) ? (array) $data['metadata'] : [],
            enabled: isset($data['enabled']) ? (bool) $data['enabled'] : true,
            active: isset($data['active']) ? (bool) $data['active'] : false,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromPersistedArray(array $data): self
    {
        $basePath = (string) ($data['base_path'] ?? throw new \InvalidArgumentException('Theme base_path missing.'));

        return new self(
            slug: (string) ($data['slug'] ?? throw new \InvalidArgumentException('Theme slug missing.')),
            name: (string) ($data['name'] ?? throw new \InvalidArgumentException('Theme name missing.')),
            description: (string) ($data['description'] ?? throw new \InvalidArgumentException('Theme description missing.')),
            basePath: $basePath,
            version: isset($data['version']) ? (string) $data['version'] : null,
            priority: (int) ($data['priority'] ?? 0),
            services: isset($data['services']) ? (string) $data['services'] : null,
            assets: isset($data['assets']) ? (string) $data['assets'] : 'assets',
            repository: isset($data['repository']) ? (string) $data['repository'] : null,
            metadata: isset($data['metadata']) ? (array) $data['metadata'] : [],
            enabled: isset($data['enabled']) ? (bool) $data['enabled'] : true,
            active: isset($data['active']) ? (bool) $data['active'] : false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'base_path' => $this->basePath,
            'version' => $this->version,
            'priority' => $this->priority,
            'services' => $this->services,
            'assets' => $this->assets,
            'repository' => $this->repository,
            'metadata' => $this->metadata,
            'enabled' => $this->enabled,
            'active' => $this->active,
        ];
    }

    public function servicesPath(): ?string
    {
        if ($this->services === null) {
            return null;
        }

        return $this->resolve($this->services);
    }

    public function assetsPath(): ?string
    {
        if ($this->assets === null) {
            return null;
        }

        $path = $this->resolve($this->assets);

        return is_dir($path) ? $path : null;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withState(bool $enabled, array $metadata): self
    {
        return new self(
            slug: $this->slug,
            name: $this->name,
            description: $this->description,
            basePath: $this->basePath,
            version: $this->version,
            priority: $this->priority,
            services: $this->services,
            assets: $this->assets,
            repository: $this->repository,
            metadata: $metadata,
            enabled: $enabled,
            active: $this->active,
        );
    }

    public function withActivation(bool $enabled, bool $active, array $metadata): self
    {
        return new self(
            slug: $this->slug,
            name: $this->name,
            description: $this->description,
            basePath: $this->basePath,
            version: $this->version,
            priority: $this->priority,
            services: $this->services,
            assets: $this->assets,
            repository: $this->repository,
            metadata: $metadata,
            enabled: $enabled,
            active: $active,
        );
    }

    private function resolve(string $relative): string
    {
        return rtrim($this->basePath, '/').'/'.ltrim($relative, '/');
    }
}
