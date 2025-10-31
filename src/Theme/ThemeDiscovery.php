<?php

declare(strict_types=1);

namespace App\Theme;

use Symfony\Component\Yaml\Yaml;

final class ThemeDiscovery
{
    private const MANIFEST_PHP = 'theme.php';
    private const MANIFEST_YAML = 'theme.yaml';

    public function __construct(
        private readonly string $themesDirectory,
    ) {
    }

    /**
     * @return list<ThemeManifest>
     */
    public function discover(): array
    {
        if (!is_dir($this->themesDirectory)) {
            return [];
        }

        $manifests = [];

        $directories = glob($this->themesDirectory.'/*', \GLOB_ONLYDIR) ?: [];
        foreach ($directories as $directory) {
            $manifest = $this->loadManifest($directory);

            if ($manifest === null) {
                continue;
            }

            $manifests[] = $manifest;
        }

        usort($manifests, static fn (ThemeManifest $a, ThemeManifest $b): int => $b->priority <=> $a->priority);

        return $manifests;
    }

    private function loadManifest(string $basePath): ?ThemeManifest
    {
        $phpManifest = $basePath.'/'.self::MANIFEST_PHP;
        if (is_file($phpManifest)) {
            $result = require $phpManifest;

            if ($result instanceof ThemeManifest) {
                return $result;
            }

            if (is_array($result)) {
                return ThemeManifest::fromArray($result, $basePath);
            }

            throw new \RuntimeException(sprintf('Theme definition in "%s" must return array or ThemeManifest.', $phpManifest));
        }

        $yamlManifest = $basePath.'/'.self::MANIFEST_YAML;
        if (is_file($yamlManifest)) {
            $data = Yaml::parseFile($yamlManifest);

            if (!is_array($data)) {
                throw new \RuntimeException(sprintf('Theme definition in "%s" must contain an object/array.', $yamlManifest));
            }

            return ThemeManifest::fromArray($data, $basePath);
        }

        return null;
    }
}
