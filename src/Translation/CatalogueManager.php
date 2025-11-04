<?php

declare(strict_types=1);

namespace App\Translation;

use App\Internationalization\LocaleProvider;
use App\Module\ModuleManifest;
use App\Module\ModuleRegistry;
use App\Theme\ThemeManifest;
use App\Theme\ThemeRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CatalogueManager
{
    /**
     * @var array<string, string> keyed by locale => fingerprint
     */
    private array $loaded = [];

    /**
     * @var array<string, LoaderInterface>
     */
    private array $loaders;

    public function __construct(
        private readonly Translator $translator,
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ThemeRegistry $themeRegistry,
        private readonly LocaleProvider $localeProvider,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly CacheInterface $cache,
    ) {
        $this->translator->addLoader('array', new ArrayLoader());
        $this->loaders = [
            'yml' => new YamlFileLoader(),
            'yaml' => new YamlFileLoader(),
            'xlf' => new XliffFileLoader(),
            'xliff' => new XliffFileLoader(),
            'php' => new PhpFileLoader(),
            'json' => new JsonFileLoader(),
        ];
    }

    public function ensureLocale(string $locale): void
    {
        $resources = $this->collectResources($locale);
        $fingerprint = $this->computeFingerprint($resources);
        $cacheKey = 'translations.catalogue.'.sha1($locale.'|'.$fingerprint);

        if (($this->loaded[$locale] ?? null) === $fingerprint) {
            return;
        }

        $catalogue = $this->cache->get($cacheKey, function (ItemInterface $item) use ($resources, $locale) {
            return $this->mergeResources($resources, $locale);
        });

        foreach ($catalogue as $domain => $messages) {
            if ($messages === []) {
                continue;
            }

            $this->translator->addResource('array', $messages, $locale, (string) $domain);
        }

        $this->loaded[$locale] = $fingerprint;
    }

    public function getFallbackLocale(): string
    {
        return $this->localeProvider->fallback();
    }

    /**
     * @param list<array{path: string, format: string, domain: string}> $resources
     */
    private function mergeResources(array $resources, string $locale): array
    {
        $catalogue = [];

        foreach ($resources as $resource) {
            $loader = $this->loaders[$resource['format']] ?? null;

            if ($loader === null) {
                continue;
            }

            try {
                $messages = $loader->load($resource['path'], $locale, $resource['domain'])->all($resource['domain']);
            } catch (\Throwable) {
                continue;
            }

            if (!isset($catalogue[$resource['domain']])) {
                $catalogue[$resource['domain']] = [];
            }

            foreach ($messages as $key => $value) {
                if (!\array_key_exists($key, $catalogue[$resource['domain']])) {
                    $catalogue[$resource['domain']][$key] = $value;
                }
            }
        }

        return $catalogue;
    }

    /**
     * @return list<array{path: string, format: string, domain: string}>
     */
    private function collectResources(string $locale): array
    {
        $resources = [];

        $addResources = function (?string $directory) use (&$resources, $locale): void {
            if ($directory === null) {
                return;
            }

            foreach ($this->findLocaleFiles($directory, $locale) as $resource) {
                $resources[] = $resource;
            }
        };

        $activeTheme = $this->themeRegistry->active();
        $addResources($this->themeTranslationsPath($activeTheme));

        $modules = $this->moduleRegistry->enabled();
        usort(
            $modules,
            static fn (ModuleManifest $a, ModuleManifest $b): int => $b->priority <=> $a->priority
        );

        foreach ($modules as $manifest) {
            $addResources($this->moduleTranslationsPath($manifest));
        }

        $fallbackTheme = $this->fallbackTheme($activeTheme);
        $addResources($this->themeTranslationsPath($fallbackTheme));

        $addResources($this->projectDir.'/translations');

        return $resources;
    }

    /**
     * @param list<array{path: string, format: string, domain: string}> $resources
     */
    private function computeFingerprint(array $resources): string
    {
        if ($resources === []) {
            return 'empty';
        }

        $pieces = [];

        foreach ($resources as $resource) {
            $mtime = @filemtime($resource['path']);
            $pieces[] = $resource['path'].':'.($mtime === false ? '0' : (string) $mtime);
        }

        return sha1(implode('|', $pieces));
    }

    /**
     * @return list<array{path: string, format: string, domain: string}>
     */
    private function findLocaleFiles(string $directory, string $locale): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $resources = [];
        $iterator = new \DirectoryIterator($directory);

        foreach ($iterator as $fileinfo) {
            if (!$fileinfo->isFile()) {
                continue;
            }

            $parts = explode('.', $fileinfo->getFilename());
            if (\count($parts) < 3) {
                continue;
            }

            $fileLocale = $parts[\count($parts) - 2] ?? '';
            if ($fileLocale !== $locale) {
                continue;
            }

            $format = strtolower($parts[\count($parts) - 1]);
            $domain = implode('.', \array_slice($parts, 0, -2));

            $resources[] = [
                'path' => $fileinfo->getPathname(),
                'format' => $format,
                'domain' => $domain,
            ];
        }

        return $resources;
    }

    private function moduleTranslationsPath(ModuleManifest $manifest): ?string
    {
        return $manifest->translationsPath();
    }

    private function themeTranslationsPath(?ThemeManifest $manifest): ?string
    {
        return $manifest?->translationsPath();
    }

    private function fallbackTheme(?ThemeManifest $active): ?ThemeManifest
    {
        $fallback = $this->themeRegistry->find('base');

        if ($fallback === null) {
            return null;
        }

        if ($active !== null && $fallback->slug === $active->slug) {
            return null;
        }

        if (!$fallback->enabled) {
            return null;
        }

        return $fallback;
    }
}
