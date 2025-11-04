<?php

declare(strict_types=1);

namespace App\Internationalization;

use App\Module\ModuleRegistry;
use App\Theme\ThemeManifest;
use App\Theme\ThemeRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class LocaleProvider
{
    /** @var list<string> */
    private array $cache;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ThemeRegistry $themeRegistry,
    ) {
        $this->cache = [];
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        if ($this->cache !== []) {
            return $this->cache;
        }

        $locales = ['en'];

        foreach ($this->translationDirectories() as $directory) {
            foreach (scandir($directory) ?: [] as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $path = $directory.'/'.$file;
                if (!is_file($path)) {
                    continue;
                }

                $parts = explode('.', $file);
                if (count($parts) < 3) {
                    continue;
                }

                $locale = $parts[count($parts) - 2] ?? '';
                if ($locale === '' || !\is_string($locale)) {
                    continue;
                }

                $locales[] = $locale;
            }
        }

        $locales = array_values(array_unique($locales));
        sort($locales);

        return $this->cache = $locales;
    }

    public function isSupported(string $locale): bool
    {
        return in_array($locale, $this->available(), true);
    }

    public function fallback(): string
    {
        return 'en';
    }

    /**
     * @return list<string>
     */
    private function translationDirectories(): array
    {
        $directories = [$this->projectDir.'/translations'];

        if (($activeTheme = $this->themeRegistry->active()) instanceof ThemeManifest) {
            $path = $activeTheme->translationsPath();
            if ($path !== null) {
                $directories[] = $path;
            }
        }

        $fallbackTheme = $this->themeRegistry->find('base');
        if ($fallbackTheme instanceof ThemeManifest) {
            $path = $fallbackTheme->translationsPath();
            if ($path !== null) {
                $directories[] = $path;
            }
        }

        foreach ($this->moduleRegistry->enabled() as $manifest) {
            $path = $manifest->translationsPath();
            if ($path !== null) {
                $directories[] = $path;
            }
        }

        $directories = array_filter($directories, static fn (string $dir): bool => is_dir($dir));

        return array_values(array_unique($directories));
    }
}
