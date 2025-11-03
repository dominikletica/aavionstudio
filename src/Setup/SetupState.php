<?php

declare(strict_types=1);

namespace App\Setup;

use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Filesystem\Filesystem;

final class SetupState
{
    private readonly string $systemDatabasePath;

    public function __construct(
        private readonly string $databaseUrl,
        private readonly string $systemDatabaseFallbackPath,
        private readonly string $userDatabasePath,
        private readonly string $lockFilePath,
        private readonly Filesystem $filesystem,
    ) {
        $this->systemDatabasePath = $this->resolveSystemDatabasePath($databaseUrl) ?? $systemDatabaseFallbackPath;
    }

    public function primaryDatabasePath(): string
    {
        return $this->systemDatabasePath;
    }

    public function userDatabasePath(): string
    {
        return $this->userDatabasePath;
    }

    public function lockFilePath(): string
    {
        return $this->lockFilePath;
    }

    public function isCompleted(): bool
    {
        return $this->filesystem->exists($this->lockFilePath);
    }

    public function markCompleted(): void
    {
        $this->filesystem->dumpFile($this->lockFilePath, (string) time());
    }

    public function clearLock(): void
    {
        if ($this->filesystem->exists($this->lockFilePath)) {
            $this->filesystem->remove($this->lockFilePath);
        }
    }

    public function databasesExist(): bool
    {
        return $this->filesystem->exists($this->systemDatabasePath)
            && $this->filesystem->exists($this->userDatabasePath);
    }

    public function missingDatabases(): bool
    {
        return ! $this->filesystem->exists($this->systemDatabasePath)
            || ! $this->filesystem->exists($this->userDatabasePath);
    }

    private function resolveSystemDatabasePath(string $databaseUrl): ?string
    {
        if ($databaseUrl === '') {
            return null;
        }

        try {
            $params = (new DsnParser())->parse($databaseUrl);
        } catch (\Throwable) {
            return null;
        }

        $path = $params['path'] ?? null;

        if (\is_string($path) && $path !== '') {
            return $path;
        }

        $url = $params['url'] ?? null;

        if (\is_string($url) && $url !== '') {
            $components = parse_url($url);

            if (\is_array($components) && isset($components['path']) && $components['path'] !== '') {
                return $components['path'];
            }
        }

        return null;
    }
}
