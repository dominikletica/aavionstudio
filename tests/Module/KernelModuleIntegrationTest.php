<?php

declare(strict_types=1);

namespace App\Tests\Module;

use App\Module\ModuleManifest;
use App\Module\ModuleRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class KernelModuleIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testCoreModuleManifestIsRegistered(): void
    {
        static::bootKernel();

        $container = static::getContainer();

        if ($container->has(ModuleRegistry::class)) {
            $registry = $container->get(ModuleRegistry::class);
            \assert($registry instanceof ModuleRegistry);
        } else {
            $manifestsData = $container->getParameter('app.modules');
            \assert(\is_array($manifestsData));
            $registry = new ModuleRegistry($manifestsData);
        }

        $manifest = $registry->find('core');

        self::assertInstanceOf(ModuleManifest::class, $manifest);
        self::assertSame('Core Platform', $manifest->name);
        self::assertTrue($manifest->enabled);
        self::assertSame('https://github.com/dominikletica/aavionstudio', $manifest->repository);

        $parameter = $container->getParameter('app.module.core.name');
        self::assertSame('Core Platform', $parameter);

        $capabilities = $container->getParameter('app.capabilities');
        self::assertIsArray($capabilities);
    }
}
