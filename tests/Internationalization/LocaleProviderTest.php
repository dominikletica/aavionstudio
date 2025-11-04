<?php

declare(strict_types=1);

namespace App\Tests\Internationalization;

use App\Internationalization\LocaleProvider;
use PHPUnit\Framework\TestCase;

final class LocaleProviderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectDir = sys_get_temp_dir().'/locale_provider_'.bin2hex(random_bytes(4));
        mkdir($this->projectDir.'/translations', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
        parent::tearDown();
    }

    public function testReturnsEnglishWhenNoTranslationsPresent(): void
    {
        $provider = new LocaleProvider($this->projectDir);
        self::assertSame(['en'], $provider->available());
        self::assertTrue($provider->isSupported('en'));
    }

    public function testDetectsLocalesFromTranslationFiles(): void
    {
        file_put_contents($this->projectDir.'/translations/messages.de.yaml', "");
        file_put_contents($this->projectDir.'/translations/validators.fr.xlf', "");

        $provider = new LocaleProvider($this->projectDir);
        self::assertSame(['de', 'en', 'fr'], $provider->available());
        self::assertTrue($provider->isSupported('fr'));
        self::assertFalse($provider->isSupported('es'));
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
