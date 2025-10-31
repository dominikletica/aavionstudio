<?php

declare(strict_types=1);

namespace App\Asset;

use App\Module\ModuleRegistry;
use App\Theme\ThemeRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AssetStateTracker
{
    private const STATE_FILENAME = 'assets-state.json';

    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ThemeRegistry $themeRegistry,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%kernel.cache_dir%')] private readonly string $cacheDir,
    ) {
    }

    /**
     * @return array{
     *     modules: array<string, string>,
     *     themes: array<string, string>
     * }
     */
    public function currentState(): array
    {
        return [
            'modules' => $this->hashModuleAssets(),
            'themes' => $this->hashThemeAssets(),
        ];
    }

    public function isUpToDate(array $state): bool
    {
        return $state === $this->readPersistedState();
    }

    public function writeState(array $state): void
    {
        $stateFile = $this->getStateFilePath();
        $directory = \dirname($stateFile);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Unable to create directory "%s" for asset state cache.', $directory));
            }
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode asset state JSON.');
        }

        if (file_put_contents($stateFile, $json) === false) {
            throw new \RuntimeException(sprintf('Failed to write asset state cache to "%s".', $stateFile));
        }
    }

    public function readPersistedState(): array
    {
        $stateFile = $this->getStateFilePath();

        if (!is_file($stateFile)) {
            return [
                'modules' => [],
                'themes' => [],
            ];
        }

        $contents = file_get_contents($stateFile);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Failed to read asset state cache at "%s".', $stateFile));
        }

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        return [
            'modules' => (array) ($decoded['modules'] ?? []),
            'themes' => (array) ($decoded['themes'] ?? []),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function hashModuleAssets(): array
    {
        $hashes = [];
        foreach ($this->moduleRegistry->all() as $manifest) {
            $assetsPath = $manifest->assetsPath();

            if ($assetsPath === null || !is_dir($assetsPath)) {
                continue;
            }

            $hashes[$manifest->slug] = $this->hashDirectory($assetsPath);
        }

        ksort($hashes);

        return $hashes;
    }

    /**
     * @return array<string, string>
     */
    private function hashThemeAssets(): array
    {
        $hashes = [];
        foreach ($this->themeRegistry->all() as $manifest) {
            $assetsPath = $manifest->assetsPath();

            if ($assetsPath === null || !is_dir($assetsPath)) {
                continue;
            }

            $hashes[$manifest->slug] = $this->hashDirectory($assetsPath);
        }

        ksort($hashes);

        return $hashes;
    }

    private function hashDirectory(string $path): string
    {
        $context = hash_init('sha256');
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        foreach ($files as $filePath) {
            $relativePath = substr($filePath, \strlen($path));
            hash_update($context, $relativePath);
            $size = filesize($filePath);
            hash_update($context, (string) $size);
            hash_update_file($context, $filePath);
        }

        return hash_final($context);
    }

    private function getStateFilePath(): string
    {
        return rtrim($this->cacheDir, '/').'/'.self::STATE_FILENAME;
    }
}
