<?php

namespace App;

use App\Module\ModuleDiscovery;
use App\Module\ModuleManifest;
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

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader): void
    {
        $confDir = $this->getProjectDir().'/config';

        $container->import($confDir.'/{packages}/*.yaml');
        $container->import($confDir.'/{packages}/'.$this->environment.'/*.yaml');

        $manifests = $this->discoverModules();
        $container->parameters()->set(
            'app.modules',
            array_map(static fn (ModuleManifest $manifest): array => $manifest->toArray(), $manifests),
        );

        foreach ($manifests as $manifest) {
            if ($servicesPath = $manifest->servicesPath()) {
                $container->import($servicesPath);
            }
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $confDir = $this->getProjectDir().'/config';

        $routes->import($confDir.'/routes.yaml');
        $routes->import($confDir.'/routes/'.$this->environment.'/*.yaml');

        foreach ($this->discoverModules() as $manifest) {
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
        $manifests = $discovery->discover();

        return $this->discoveredModules = $manifests;
    }
}
