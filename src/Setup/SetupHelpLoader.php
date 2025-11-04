<?php

declare(strict_types=1);

namespace App\Setup;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final class SetupHelpLoader
{
    private const DEFAULT_LOCALE = 'en';
    private const HELP_DIR = '/';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function load(string $locale): array
    {
        $entries = $this->readEntries($locale);

        $grouped = [];
        foreach ($entries as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $section = \is_string($entry['section'] ?? null) ? $entry['section'] : 'general';
            $grouped[$section][] = $entry;
        }

        return $grouped;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readEntries(string $locale): array
    {
        $paths = [];

        $paths[] = $this->projectDir.self::HELP_DIR.'help.json';

        if ($locale !== '' && $locale !== self::DEFAULT_LOCALE) {
            $paths[] = $this->projectDir.self::HELP_DIR.sprintf('help.%s.json', self::DEFAULT_LOCALE);
        }

        if ($locale !== '') {
            $paths[] = $this->projectDir.self::HELP_DIR.sprintf('help.%s.json', $locale);
        }

        $merged = [];

        foreach (array_unique($paths) as $path) {
            if (!is_file($path)) {
                continue;
            }

            $raw = (string) file_get_contents($path);
            $decoded = json_decode($raw, true);

            if (\is_array($decoded)) {
                $merged = array_merge($merged, $decoded);
            }
        }

        return $merged;
    }
}
