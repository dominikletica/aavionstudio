<?php

declare(strict_types=1);

namespace App\Internationalization;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class LocaleProvider
{
    /** @var list<string> */
    private array $cache;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
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
        $translationDir = $this->projectDir.'/translations';

        if (is_dir($translationDir)) {
            foreach (scandir($translationDir) ?: [] as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $path = $translationDir.'/'.$file;
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
}
