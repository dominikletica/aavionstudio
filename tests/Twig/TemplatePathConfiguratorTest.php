<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Module\ModuleManifest;
use App\Module\ModuleRegistry;
use App\Theme\ThemeManifest;
use App\Theme\ThemeRegistry;
use App\Twig\TemplatePathConfigurator;
use PHPUnit\Framework\TestCase;
use Twig\Loader\FilesystemLoader;

final class TemplatePathConfiguratorTest extends TestCase
{
    public function testConfigurePrependPathsInExpectedOrder(): void
    {
        $workspace = sys_get_temp_dir().'/aavion_tpl_'.uniqid('', true);
        mkdir($workspace);

        $baseTemplates = $workspace.'/base';
        mkdir($baseTemplates);

        $activeThemeDir = $workspace.'/theme-active';
        mkdir($activeThemeDir);
        mkdir($activeThemeDir.'/templates');

        $moduleHighDir = $workspace.'/module-high';
        mkdir($moduleHighDir);
        mkdir($moduleHighDir.'/templates');

        $moduleLowDir = $workspace.'/module-low';
        mkdir($moduleLowDir);
        mkdir($moduleLowDir.'/templates');

        $loader = new FilesystemLoader([$baseTemplates]);

        $activeTheme = new ThemeManifest(
            slug: 'ocean',
            name: 'Ocean',
            description: 'Ocean theme',
            basePath: $activeThemeDir,
            version: '1.0.0',
            priority: 10,
            services: null,
            assets: 'assets',
            repository: null,
            metadata: [],
            enabled: true,
            active: true,
        );

        $baseTheme = new ThemeManifest(
            slug: 'base',
            name: 'Base',
            description: 'Base fallback',
            basePath: $workspace.'/base-theme',
            version: '1.0.0',
            priority: 1000,
            services: null,
            assets: 'assets',
            repository: null,
            metadata: ['locked' => true],
            enabled: true,
            active: false,
        );

        $themeRegistry = new ThemeRegistry([
            $activeTheme->toArray(),
            $baseTheme->toArray(),
        ]);

        $moduleHigh = new ModuleManifest(
            slug: 'blog',
            name: 'Blog',
            description: 'Blog module',
            basePath: $moduleHighDir,
            priority: 200,
            services: null,
            routes: null,
            repository: null,
            navigation: [],
            capabilities: [],
            metadata: [],
            enabled: true,
        );

        $moduleLow = new ModuleManifest(
            slug: 'analytics',
            name: 'Analytics',
            description: 'Analytics module',
            basePath: $moduleLowDir,
            priority: 50,
            services: null,
            routes: null,
            repository: null,
            navigation: [],
            capabilities: [],
            metadata: [],
            enabled: true,
        );

        $moduleRegistry = new ModuleRegistry([
            $moduleHigh->toArray(),
            $moduleLow->toArray(),
        ]);

        $configurator = new TemplatePathConfigurator($loader, $themeRegistry, $moduleRegistry);
        $configurator->configure();

        $paths = $loader->getPaths();

        self::assertSame([
            realpath($activeThemeDir.'/templates'),
            realpath($moduleHighDir.'/templates'),
            realpath($moduleLowDir.'/templates'),
            $baseTemplates,
        ], $paths);

        $this->deleteDirectory($workspace);
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
