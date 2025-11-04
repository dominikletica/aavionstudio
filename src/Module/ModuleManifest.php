<?php

declare(strict_types=1);

namespace App\Module;

final class ModuleManifest
{
    /**
     * @param list<array<string, mixed>> $navigation
     * @param list<array<string, mixed>> $capabilities
     * @param array<string, mixed>        $metadata
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly string $basePath,
        public readonly int $priority = 0,
        public readonly ?string $services = null,
        public readonly ?string $routes = null,
        public readonly ?string $repository = null,
        public readonly array $navigation = [],
        public readonly array $capabilities = [],
        public readonly array $metadata = [],
        public readonly bool $enabled = true,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $basePath): self
    {
        return new self(
            slug: (string) ($data['slug'] ?? throw new \InvalidArgumentException('Module slug missing.')),
            name: (string) ($data['name'] ?? throw new \InvalidArgumentException('Module name missing.')),
            description: (string) ($data['description'] ?? throw new \InvalidArgumentException('Module description missing.')),
            basePath: $basePath,
            priority: (int) ($data['priority'] ?? 0),
            services: isset($data['services']) ? (string) $data['services'] : null,
            routes: isset($data['routes']) ? (string) $data['routes'] : null,
            repository: isset($data['repository']) ? (string) $data['repository'] : null,
            navigation: isset($data['navigation']) ? (array) $data['navigation'] : [],
            capabilities: isset($data['capabilities']) ? (array) $data['capabilities'] : [],
            metadata: isset($data['metadata']) ? (array) $data['metadata'] : [],
            enabled: isset($data['enabled']) ? (bool) $data['enabled'] : true,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromPersistedArray(array $data): self
    {
        $basePath = (string) ($data['base_path'] ?? throw new \InvalidArgumentException('Module base_path missing.'));

        return new self(
            slug: (string) ($data['slug'] ?? throw new \InvalidArgumentException('Module slug missing.')),
            name: (string) ($data['name'] ?? throw new \InvalidArgumentException('Module name missing.')),
            description: (string) ($data['description'] ?? throw new \InvalidArgumentException('Module description missing.')),
            basePath: $basePath,
            priority: (int) ($data['priority'] ?? 0),
            services: isset($data['services']) ? (string) $data['services'] : null,
            routes: isset($data['routes']) ? (string) $data['routes'] : null,
            repository: isset($data['repository']) ? (string) $data['repository'] : null,
            navigation: isset($data['navigation']) ? (array) $data['navigation'] : [],
            capabilities: isset($data['capabilities']) ? (array) $data['capabilities'] : [],
            metadata: isset($data['metadata']) ? (array) $data['metadata'] : [],
            enabled: isset($data['enabled']) ? (bool) $data['enabled'] : true,
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
            'priority' => $this->priority,
            'services' => $this->services,
            'routes' => $this->routes,
            'repository' => $this->repository,
            'navigation' => $this->navigation,
            'capabilities' => $this->capabilities,
            'metadata' => $this->metadata,
            'enabled' => $this->enabled,
        ];
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
            priority: $this->priority,
            services: $this->services,
            routes: $this->routes,
            repository: $this->repository,
            navigation: $this->navigation,
            capabilities: $this->capabilities,
            metadata: $metadata,
            enabled: $enabled,
        );
    }

    public function servicesPath(): ?string
    {
        if ($this->services === null) {
            return null;
        }

        return $this->resolve($this->services);
    }

    public function routesPath(): ?string
    {
        if ($this->routes === null) {
            return null;
        }

        return $this->resolve($this->routes);
    }

    public function assetsPath(): ?string
    {
        $assetsDir = $this->resolve('assets');

        return is_dir($assetsDir) ? $assetsDir : null;
    }

    public function translationsPath(): ?string
    {
        $translationsDir = $this->resolve('translations');

        return is_dir($translationsDir) ? $translationsDir : null;
    }

    private function resolve(string $relative): string
    {
        return rtrim($this->basePath, '/').'/'.ltrim($relative, '/');
    }
}
