<?php

declare(strict_types=1);

namespace App\Twig;

use App\Module\ModuleManifest;
use App\Module\ModuleRegistry;
use App\Theme\ThemeManifest;
use App\Theme\ThemeRegistry;
use Twig\Loader\FilesystemLoader;

final class TemplatePathConfigurator
{
    private ?array $defaultPaths = null;

    public function __construct(
        private readonly FilesystemLoader $loader,
        private readonly ThemeRegistry $themeRegistry,
        private readonly ModuleRegistry $moduleRegistry,
    ) {
    }

    public function configure(): void
    {
        if ($this->defaultPaths === null) {
            $this->defaultPaths = $this->loader->getPaths();
        }

        $paths = [];

        $activeTheme = $this->themeRegistry->active();
        if ($activeTheme !== null && $activeTheme->slug !== 'base') {
            $themeTemplates = $activeTheme->basePath.'/templates';
            if (is_dir($themeTemplates)) {
                $paths[] = realpath($themeTemplates) ?: $themeTemplates;
            }
        }

        $modules = array_filter(
            $this->moduleRegistry->all(),
            static fn (ModuleManifest $manifest): bool => $manifest->enabled
        );

        usort(
            $modules,
            static fn (ModuleManifest $a, ModuleManifest $b): int => ($b->priority <=> $a->priority) ?: strcmp($a->slug, $b->slug)
        );

        foreach ($modules as $manifest) {
            $moduleTemplates = $manifest->basePath.'/templates';
            if (!is_dir($moduleTemplates)) {
                continue;
            }

            $paths[] = realpath($moduleTemplates) ?: $moduleTemplates;
        }

        $paths = array_values(array_unique($paths));

        $basePaths = [];
        foreach ($this->defaultPaths as $path) {
            $resolved = realpath($path) ?: $path;
            if (!in_array($resolved, $paths, true)) {
                $basePaths[] = $path;
            }
        }

        $this->loader->setPaths(array_merge($paths, $basePaths));
    }
}
