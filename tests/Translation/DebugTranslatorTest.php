<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use App\Internationalization\LocaleProvider;
use App\Module\ModuleRegistry;
use App\Theme\ThemeRegistry;
use App\Translation\CatalogueManager;
use App\Translation\DebugTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class DebugTranslatorTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }

        parent::tearDown();
    }

    public function testFallsBackToInnerTranslatorWhenDebugKeysDisabled(): void
    {
        $inner = $this->createTranslator();
        $stack = new RequestStack();

        $manager = $this->createManager($inner);
        $translator = new DebugTranslator($inner, $stack, $manager);

        self::assertSame('Tip', $translator->trans('ui.tip'));
    }

    public function testReturnsKeyWhenDebugKeysEnabled(): void
    {
        $inner = $this->createTranslator();
        $stack = new RequestStack();

        $manager = $this->createManager($inner);
        $translator = new DebugTranslator($inner, $stack, $manager);

        $request = Request::create('/');
        $request->attributes->set('_translation_debug_keys', true);
        $stack->push($request);

        self::assertSame('ui.tip', $translator->trans('ui.tip'));
    }

    private function createTranslator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['ui.tip' => 'Tip'], 'en', 'messages');

        return $translator;
    }

    private function createManager(Translator $translator): CatalogueManager
    {
        $projectDir = sys_get_temp_dir().'/debug_translator_'.bin2hex(random_bytes(4));
        mkdir($projectDir.'/translations', 0777, true);
        $this->tempDirs[] = $projectDir;

        $moduleRegistry = new ModuleRegistry([]);
        $themeRegistry = new ThemeRegistry([]);
        $localeProvider = new LocaleProvider($projectDir, $moduleRegistry, $themeRegistry);

        return new CatalogueManager(
            $translator,
            $moduleRegistry,
            $themeRegistry,
            $localeProvider,
            $projectDir,
            new ArrayAdapter(),
        );
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
