<?php

namespace App;

use App\Module\ModuleDiscovery;
use App\Module\ModuleManifest;
use App\Module\ModuleStateSynchronizer;
use App\Theme\ThemeDiscovery;
use App\Theme\ThemeManifest;
use App\Theme\ThemeStateSynchronizer;
use App\Twig\TemplatePathConfigurator;
use App\Setup\MigrationSynchronizer;
use App\Security\Capability\CapabilitySynchronizer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @var list<ModuleManifest>|null
     */
    private ?array $discoveredModules = null;

    /**
     * @var list<ThemeManifest>|null
     */
    private ?array $discoveredThemes = null;

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader): void
    {
        $confDir = $this->getProjectDir().'/config';

        $container->import($confDir.'/{packages}/*.yaml');
        $container->import($confDir.'/{packages}/'.$this->environment.'/*.yaml');

        if (is_file($confDir.'/services.yaml')) {
            $container->import($confDir.'/services.yaml');
        }

        if (is_file($confDir.'/services_'.$this->environment.'.yaml')) {
            $container->import($confDir.'/services_'.$this->environment.'.yaml');
        }

        $manifests = $this->discoverModules();
        $activeManifests = array_filter(
            $manifests,
            static fn (ModuleManifest $manifest): bool => $manifest->enabled,
        );

        $persisted = array_map(static fn (ModuleManifest $manifest): array => $manifest->toArray(), $manifests);

        $container->parameters()->set('app.modules', $persisted);

        $registryPreview = new \App\Module\ModuleRegistry($persisted);

        $container->parameters()->set('app.capabilities', $registryPreview->capabilities());

        foreach ($activeManifests as $manifest) {
            if ($servicesPath = $manifest->servicesPath()) {
                $container->import($servicesPath);
            }
        }

        $themes = $this->discoverThemes();
        $persistedThemes = array_map(static fn (ThemeManifest $manifest): array => $manifest->toArray(), $themes);
        $container->parameters()->set('app.themes', $persistedThemes);

        foreach ($themes as $theme) {
            if (!$theme->enabled) {
                continue;
            }
            if ($servicesPath = $theme->servicesPath()) {
                $container->import($servicesPath);
            }
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $confDir = $this->getProjectDir().'/config';

        $routes->import($confDir.'/routes.yaml');

        $environmentRoutes = $confDir.'/routes/'.$this->environment;

        if (is_dir($environmentRoutes)) {
            $routes->import($environmentRoutes.'/*.yaml');
        }

        if (is_dir($confDir.'/routes')) {
            $routes->import($confDir.'/routes/*.yaml');
        }

        foreach ($this->discoverModules() as $manifest) {
            if (!$manifest->enabled) {
                continue;
            }
            if ($routesPath = $manifest->routesPath()) {
                $routes->import($routesPath);
            }
        }
    }

    /**
     * @return list<ModuleManifest>
     */
    private function discoverModules(): array
    {
        if ($this->discoveredModules !== null) {
            return $this->discoveredModules;
        }

        $modulesDir = $this->getProjectDir().'/modules';

        $discovery = new ModuleDiscovery($modulesDir);
        $manifests = $this->applyModuleStates($discovery->discover());

        return $this->discoveredModules = $manifests;
    }

    /**
     * @return list<ThemeManifest>
     */
    private function discoverThemes(): array
    {
        if ($this->discoveredThemes !== null) {
            return $this->discoveredThemes;
        }

        $themesDir = $this->getProjectDir().'/themes';
        $discovery = new ThemeDiscovery($themesDir);
        $manifests = $this->applyThemeStates($discovery->discover());

        return $this->discoveredThemes = $manifests;
    }

    /**
     * @param list<ThemeManifest> $manifests
     * @return list<ThemeManifest>
     */
    private function applyThemeStates(array $manifests): array
    {
        $connection = $this->createBootstrapConnection();

        if ($connection === null) {
            return $manifests;
        }

        try {
            $schemaManager = $connection->createSchemaManager();
        } catch (\Throwable) {
            return $manifests;
        }

        if (!$schemaManager->tablesExist(['app_theme_state'])) {
            return $manifests;
        }

        $states = [];
        $rows = $connection->fetchAllAssociative('SELECT name, enabled, active, metadata FROM app_theme_state');

        foreach ($rows as $row) {
            $metadata = [];

            if (isset($row['metadata'])) {
                $decoded = json_decode((string) $row['metadata'], true);

                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }

            $states[(string) $row['name']] = [
                'enabled' => ((int) $row['enabled']) === 1,
                'active' => ((int) $row['active']) === 1,
                'metadata' => $metadata,
            ];
        }

        $result = [];

        foreach ($manifests as $manifest) {
            $state = $states[$manifest->slug] ?? null;
            $enabled = $state['enabled'] ?? true;
            $active = $state['active'] ?? false;
            $metadata = array_merge($state['metadata'] ?? [], $manifest->metadata);

            if ($manifest->repository !== null) {
                $metadata['repository'] = $manifest->repository;
            }

            if (!empty($metadata['locked'])) {
                $enabled = true;
                $active = $state['active'] ?? false;
            }

            $result[] = $manifest->withActivation($enabled, $active, $metadata);
        }

        $hasActive = array_reduce($result, static fn (bool $carry, ThemeManifest $manifest): bool => $carry || $manifest->active, false);

        if (!$hasActive) {
            foreach ($result as $index => $manifest) {
                if ($manifest->slug === 'base') {
                    $metadata = $manifest->metadata;
                    $result[$index] = $manifest->withActivation(true, true, $metadata);
                    break;
                }
            }
        }

        return $result;
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->container->has(ModuleStateSynchronizer::class)) {
            $synchronizer = $this->container->get(ModuleStateSynchronizer::class);
            \assert($synchronizer instanceof ModuleStateSynchronizer);
            $synchronizer->synchronize($this->discoverModules());
        }

        if ($this->container->has(ThemeStateSynchronizer::class)) {
            $themeSynchronizer = $this->container->get(ThemeStateSynchronizer::class);
            \assert($themeSynchronizer instanceof ThemeStateSynchronizer);
            $themeSynchronizer->synchronize($this->discoverThemes());
        }

        if ($this->container->has(TemplatePathConfigurator::class)) {
            $configurator = $this->container->get(TemplatePathConfigurator::class);
            \assert($configurator instanceof TemplatePathConfigurator);
            $configurator->configure();
        }

        if ($this->container->has(CapabilitySynchronizer::class)) {
            $capabilitySynchronizer = $this->container->get(CapabilitySynchronizer::class);
            \assert($capabilitySynchronizer instanceof CapabilitySynchronizer);
            $capabilitySynchronizer->synchronize();
        }

        if ($this->container->has(MigrationSynchronizer::class)) {
            $migrationSynchronizer = $this->container->get(MigrationSynchronizer::class);
            \assert($migrationSynchronizer instanceof MigrationSynchronizer);
            $migrationSynchronizer->synchronize();
        }
    }

    /**
     * @param list<ModuleManifest> $manifests
     *
     * @return list<ModuleManifest>
     */
    private function applyModuleStates(array $manifests): array
    {
        $connection = $this->createBootstrapConnection();

        if ($connection === null) {
            return $manifests;
        }

        try {
            $schemaManager = $connection->createSchemaManager();
        } catch (\Throwable) {
            return $manifests;
        }

        $states = [];

        if ($schemaManager->tablesExist(['app_module_state'])) {
            $rows = $connection->fetchAllAssociative('SELECT name, enabled, metadata FROM app_module_state');

            foreach ($rows as $row) {
                $metadata = [];

                if (isset($row['metadata'])) {
                    $decoded = json_decode((string) $row['metadata'], true);

                    if (is_array($decoded)) {
                        $metadata = $decoded;
                    }
                }

                $states[(string) $row['name']] = [
                    'enabled' => ((int) $row['enabled']) === 1,
                    'metadata' => $metadata,
                ];
            }
        }

        $result = [];

        foreach ($manifests as $manifest) {
            $state = $states[$manifest->slug] ?? null;
            $enabled = $state['enabled'] ?? true;
            $metadata = array_merge($state['metadata'] ?? [], $manifest->metadata);

            if ($manifest->repository !== null) {
                $metadata['repository'] = $manifest->repository;
            }

            if (!empty($metadata['locked'])) {
                $enabled = true;
            }

            $result[] = $manifest->withState($enabled, $metadata);
        }

        return $result;
    }

    private function createBootstrapConnection(): ?Connection
    {
        $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? null;

        if ($databaseUrl === null || $databaseUrl === '') {
            return null;
        }

        try {
            $params = (new DsnParser())->parse($databaseUrl);
        } catch (\Throwable) {
            return null;
        }

        $path = $params['path'] ?? null;

        if (\is_string($path) && $path !== '' && !\is_file($path)) {
            return null;
        }

        $url = $params['url'] ?? null;

        if (\is_string($url) && \str_starts_with($url, 'sqlite')) {
            $components = parse_url($url);

            if (\is_array($components) && isset($components['path']) && $components['path'] !== '' && !\is_file($components['path'])) {
                return null;
            }
        }

        try {
            $connection = DriverManager::getConnection($params);
        } catch (\Throwable) {
            return null;
        }

        return $connection;
    }
}
