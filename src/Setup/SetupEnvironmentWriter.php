<?php

declare(strict_types=1);

namespace App\Setup;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;
use Symfony\Component\Filesystem\Filesystem;

final class SetupEnvironmentWriter
{
    private const ENV_FILE = '/.env';
    private const ENV_LOCAL_FILE = '/.env.local';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * @param array<string, string> $environmentOverrides
     * @param array<string, mixed>  $storageConfig
     */
    public function write(array $environmentOverrides, array $storageConfig): void
    {
        $resolved = $this->mergeEnvironment($environmentOverrides);

        $storageRoot = \is_string($storageConfig['root'] ?? null) ? trim((string) $storageConfig['root']) : '';
        if ($storageRoot !== '') {
            $resolved['APP_STORAGE_ROOT'] = $storageRoot;
        }

        $resolved = $this->normalizeEntries($resolved);
        $this->writeEnvLocal($resolved);
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function mergeEnvironment(array $overrides): array
    {
        $base = $this->loadEnvFile(self::ENV_FILE);
        $local = $this->loadEnvFile(self::ENV_LOCAL_FILE);
        $merged = array_merge($base, $local);

        foreach ($overrides as $key => $value) {
            if (!\is_string($key) || $key === '') {
                continue;
            }

            if ($value === '') {
                continue;
            }

            $merged[$key] = $value;
        }

        if (!isset($merged['APP_ENV'])) {
            $merged['APP_ENV'] = 'dev';
        }

        if (!isset($merged['APP_DEBUG'])) {
            $merged['APP_DEBUG'] = $merged['APP_ENV'] === 'prod' ? '0' : '1';
        }

        return $merged;
    }

    /**
     * @param array<string, string> $entries
     *
     * @return array<string, string>
     */
    private function normalizeEntries(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $key => $value) {
            if (!\is_string($key) || $key === '') {
                continue;
            }

            $normalized[$key] = $this->stringifyValue($value);
        }

        return $normalized;
    }

    private function writeEnvLocal(array $entries): void
    {
        $path = $this->projectDir.self::ENV_LOCAL_FILE;
        $tmpPath = $path.'.tmp';

        $contents = $this->dumpEnv($entries);

        $this->filesystem->dumpFile($tmpPath, $contents);
        $this->filesystem->chmod($tmpPath, 0640);
        $this->filesystem->rename($tmpPath, $path, true);
    }

    /**
     * @return array<string, string>
     */
    private function loadEnvFile(string $relativePath): array
    {
        $path = $this->projectDir.$relativePath;
        if (!is_file($path)) {
            return [];
        }

        $raw = (string) file_get_contents($path);

        try {
            return (new Dotenv())->parse($raw);
        } catch (FormatException) {
            return $this->fallbackParse($raw);
        }
    }

    /**
     * @return array<string, string>
     */
    private function fallbackParse(string $raw): array
    {
        $result = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim((string) $line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (\count($parts) !== 2) {
                continue;
            }

            [$key, $value] = $parts;
            $key = trim($key);
            $value = $this->stripQuotes(trim($value));

            if ($key !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function stripQuotes(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if ($value[0] === '"' && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
            $value = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
        }

        return $value;
    }

    /**
     * @param array<string, string> $entries
     */
    private function dumpEnv(array $entries): string
    {
        ksort($entries);

        $lines = [];
        foreach ($entries as $key => $value) {
            $lines[] = sprintf('%s=%s', $key, $this->formatValue($value));
        }

        return implode("\n", $lines)."\n";
    }

    private function stringifyValue(string $value): string
    {
        return $value;
    }

    private function formatValue(string $value): string
    {
        if ($value === '' || preg_match('/\s|"|\'|#|=/', $value)) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

            return '"'.$escaped.'"';
        }

        return $value;
    }
}
