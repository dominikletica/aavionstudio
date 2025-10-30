<?php

declare(strict_types=1);

namespace App\Module;

use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

final class ModuleDiscovery
{
    public function __construct(
        private readonly string $modulesDirectory,
    ) {
    }

    /**
     * @return list<ModuleManifest>
     */
    public function discover(): array
    {
        if (!is_dir($this->modulesDirectory)) {
            return [];
        }

        $manifests = [];

        foreach (glob($this->modulesDirectory.'/*/module.php') ?: [] as $moduleFile) {
            $basePath = \dirname($moduleFile);

            try {
                $manifest = $this->loadManifest($moduleFile, $basePath);
            } catch (\Throwable $exception) {
                // Skip invalid module definition but capture error in metadata for tooling.
                $manifests[] = ModuleManifest::fromArray([
                    'slug' => basename($basePath),
                    'name' => basename($basePath),
                    'priority' => -9999,
                    'metadata' => [
                        'error' => $exception->getMessage(),
                        'file' => $moduleFile,
                    ],
                ], $basePath);

                continue;
            }

            $manifests[] = $manifest;
        }

        usort($manifests, static fn (ModuleManifest $a, ModuleManifest $b): int => $b->priority <=> $a->priority);

        return $manifests;
    }

    private function loadManifest(string $file, string $basePath): ModuleManifest
    {
        /** @var mixed $result */
        $result = require $file;

        if ($result instanceof ModuleManifest) {
            return $result;
        }

        if (\is_array($result)) {
            return ModuleManifest::fromArray($result, $basePath);
        }

        throw new \RuntimeException(\sprintf('Module definition in "%s" must return array or ModuleManifest.', $file));
    }
}
