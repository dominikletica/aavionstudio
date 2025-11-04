<?php

declare(strict_types=1);

namespace App\Setup;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;
use Symfony\Component\Filesystem\Filesystem;

final class SetupEnvironmentWriter
{
    /**
     * @var string[]
     */
    private const KNOWN_ENV_KEYS = [
        'APP_ENV',
        'APP_DEBUG',
        'APP_SECRET',
        'APP_PRODUCT_NAME',
        'APP_VERSION',
        'APP_CHANNEL',
        'APP_RELEASE_DATE',
        'APP_STORAGE_ROOT',
        'DATABASE_URL',
        'MESSENGER_TRANSPORT_DSN',
        'MAILER_DSN',
        'LOCK_DSN',
        'SQLITE_BUSY_TIMEOUT_MS',
        'DEFAULT_URI',
    ];

    public function __construct(
        private readonly Filesystem $filesystem,
        #[Autowire('%app.setup.env_base_path%')]
        private readonly string $baseEnvPath,
        #[Autowire('%app.setup.env_local_path%')]
        private readonly string $localEnvPath,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param array<string,string> $environmentOverrides
     * @param array<string,mixed>  $storageConfig
     */
    public function write(array $environmentOverrides, array $storageConfig): void
    {
        $baseEnv = $this->loadEnvFile($this->baseEnvPath);
        $localEnv = $this->loadEnvFile($this->localEnvPath);

        $merged = $this->mergeEnvironment($environmentOverrides, $baseEnv, $localEnv);

        $storageRoot = $this->resolveStorageRoot($storageConfig, $merged['APP_STORAGE_ROOT'] ?? null);
        if ($storageRoot !== null) {
            $merged['APP_STORAGE_ROOT'] = $storageRoot;
            $merged['DATABASE_URL'] = $this->buildDatabaseUrl($storageRoot, $merged['DATABASE_URL'] ?? null);
            $this->ensureStorageDirectories($storageRoot);
        }

        foreach (['MESSENGER_TRANSPORT_DSN', 'MAILER_DSN', 'LOCK_DSN'] as $key) {
            if (!isset($merged[$key]) && isset($baseEnv[$key])) {
                $merged[$key] = $baseEnv[$key];
            }
        }

        $entries = $this->filterEntries($merged, $localEnv);

        $this->writeEnvLocal($entries);
    }

    /**
     * @param array<string,string> $overrides
     * @param array<string,string> $base
     * @param array<string,string> $local
     *
     * @return array<string,string>
     */
    private function mergeEnvironment(array $overrides, array $base, array $local): array
    {
        $merged = array_merge($base, $local);

        $appEnv = $overrides['APP_ENV'] ?? ($merged['APP_ENV'] ?? 'dev');
        $merged['APP_ENV'] = $appEnv;

        if (array_key_exists('APP_DEBUG', $overrides)) {
            $merged['APP_DEBUG'] = ((bool) $overrides['APP_DEBUG']) ? '1' : '0';
        } elseif (!isset($merged['APP_DEBUG'])) {
            $merged['APP_DEBUG'] = $appEnv === 'prod' ? '0' : '1';
        }

        if (isset($overrides['APP_SECRET']) && trim((string) $overrides['APP_SECRET']) !== '') {
            $merged['APP_SECRET'] = trim((string) $overrides['APP_SECRET']);
        }

        return $merged;
    }

    /**
     * @param array<string,mixed> $storageConfig
     */
    private function resolveStorageRoot(array $storageConfig, ?string $current): string
    {
        $root = $storageConfig['root'] ?? null;

        if (\is_string($root) && trim($root) !== '') {
            return $this->normalizeStorageRoot($root);
        }

        if (\is_string($current) && trim($current) !== '') {
            return $this->normalizeStorageRoot($current);
        }

        return SetupConfiguration::DEFAULT_STORAGE_ROOT;
    }

    private function normalizeStorageRoot(string $root): string
    {
        $root = trim($root);

        if ($root === '') {
            return SetupConfiguration::DEFAULT_STORAGE_ROOT;
        }

        if ($this->isAbsolutePath($root)) {
            return rtrim(str_replace('\\', '/', $root), '/');
        }

        return trim(str_replace('\\', '/', $root), '/');
    }

    private function buildDatabaseUrl(string $storageRoot, ?string $existing): string
    {
        if ($storageRoot === '') {
            return $existing ?? 'sqlite:///%kernel.project_dir%/var/system.brain';
        }

        if ($this->isAbsolutePath($storageRoot)) {
            $absolute = rtrim(str_replace('\\', '/', $storageRoot), '/');
            $dbPath = $absolute.'/databases/system.brain';

            return 'sqlite://'.(str_starts_with($dbPath, '/') ? '' : '/').$dbPath;
        }

        $trimmed = trim($storageRoot, '/');
        $segments = $trimmed === '' ? '' : $trimmed.'/';

        return sprintf('sqlite:///%s', '%kernel.project_dir%/'.$segments.'databases/system.brain');
    }

    private function ensureStorageDirectories(string $storageRoot): void
    {
        $absolute = $this->resolveAbsoluteStorageRoot($storageRoot);
        $absolute = rtrim($absolute, DIRECTORY_SEPARATOR);

        $paths = [
            $absolute,
            $absolute.DIRECTORY_SEPARATOR.'databases',
            $absolute.DIRECTORY_SEPARATOR.'uploads',
            $absolute.DIRECTORY_SEPARATOR.'snapshots',
            $absolute.DIRECTORY_SEPARATOR.'backups',
        ];

        $this->filesystem->mkdir($paths, 0775);
    }

    private function resolveAbsoluteStorageRoot(string $storageRoot): string
    {
        if ($this->isAbsolutePath($storageRoot)) {
            return str_replace('/', DIRECTORY_SEPARATOR, $storageRoot);
        }

        $normalized = trim($storageRoot, '/');
        if ($normalized === '') {
            $normalized = SetupConfiguration::DEFAULT_STORAGE_ROOT;
        }

        return $this->projectDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('#^[a-zA-Z]:[\\\\/]#', $path);
    }

    /**
     * @param array<string,string> $merged
     * @param array<string,string> $local
     *
     * @return array<string,string>
     */
    private function filterEntries(array $merged, array $local): array
    {
        $keys = array_merge(self::KNOWN_ENV_KEYS, array_keys($local));
        $keys = array_unique($keys);

        $filtered = [];
        foreach ($keys as $key) {
            if (!isset($merged[$key])) {
                continue;
            }

            $filtered[$key] = $merged[$key];
        }

        ksort($filtered);

        return array_map($this->stringifyValue(...), $filtered);
    }

    private function writeEnvLocal(array $entries): void
    {
        $tmpPath = $this->localEnvPath.'.tmp';

        $contents = $this->dumpEnv($entries);

        $this->filesystem->dumpFile($tmpPath, $contents);
        $this->filesystem->chmod($tmpPath, 0640);
        $this->filesystem->rename($tmpPath, $this->localEnvPath, true);
    }

    /**
     * @return array<string,string>
     */
    private function loadEnvFile(string $path): array
    {
        if ($path === '' || !is_file($path)) {
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
     * @return array<string,string>
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
     * @param array<string,string> $entries
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
