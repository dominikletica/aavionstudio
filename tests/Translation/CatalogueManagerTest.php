<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use App\Internationalization\LocaleProvider;
use App\Module\ModuleManifest;
use App\Module\ModuleRegistry;
use App\Theme\ThemeManifest;
use App\Theme\ThemeRegistry;
use App\Translation\CatalogueManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\Translator;

final class CatalogueManagerTest extends TestCase
{
    private string $workspace;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->workspace = sys_get_temp_dir().'/catalogue_manager_'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->workspace.'/translations');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->workspace);
        parent::tearDown();
    }

    public function testThemeModuleAndFallbackTranslationsCascade(): void
    {
        $moduleDir = $this->workspace.'/modules/sample-module';
        $disabledModuleDir = $this->workspace.'/modules/disabled-module';
        $activeThemeDir = $this->workspace.'/themes/custom';
        $baseThemeDir = $this->workspace.'/themes/base';

        $this->filesystem->mkdir([
            $moduleDir.'/translations',
            $disabledModuleDir.'/translations',
            $activeThemeDir.'/translations',
            $baseThemeDir.'/translations',
        ]);

        $this->filesystem->dumpFile($this->workspace.'/translations/messages.en.yaml', implode("\n", [
            "foo: 'System Foo'",
            "bar: 'System Bar'",
            "baz: 'System Baz'",
            "qux: 'System Qux'",
            "fallbackOnly: 'Fallback EN'",
        ]));

        $this->filesystem->dumpFile($this->workspace.'/translations/messages.de.yaml', implode("\n", [
            "foo: 'System Foo DE'",
            "bar: 'System Bar DE'",
            "baz: 'System Baz DE'",
            "qux: 'System Qux DE'",
        ]));

        $this->filesystem->dumpFile($baseThemeDir.'/translations/messages.en.yaml', implode("\n", [
            "foo: 'Base Foo'",
            "baz: 'Base Baz'",
        ]));

        $this->filesystem->dumpFile($baseThemeDir.'/translations/messages.de.yaml', implode("\n", [
            "foo: 'Base Foo DE'",
            "baz: 'Base Baz DE'",
        ]));

        $this->filesystem->dumpFile($activeThemeDir.'/translations/messages.en.yaml', "foo: 'Theme Foo'");
        $this->filesystem->dumpFile($activeThemeDir.'/translations/messages.de.yaml', "foo: 'Theme Foo DE'");

        $this->filesystem->dumpFile($moduleDir.'/translations/messages.en.yaml', implode("\n", [
            "foo: 'Module Foo'",
            "bar: 'Module Bar'",
        ]));

        $this->filesystem->dumpFile($moduleDir.'/translations/messages.de.yaml', implode("\n", [
            "foo: 'Module Foo DE'",
            "bar: 'Module Bar DE'",
        ]));

        $this->filesystem->dumpFile($disabledModuleDir.'/translations/messages.en.yaml', "disabled: 'Disabled Module'");

        $moduleManifest = ModuleManifest::fromArray([
            'slug' => 'sample-module',
            'name' => 'Sample Module',
            'description' => 'Test module',
            'priority' => 10,
        ], $moduleDir);

        $disabledModuleManifest = ModuleManifest::fromArray([
            'slug' => 'disabled-module',
            'name' => 'Disabled Module',
            'description' => 'Should be ignored',
            'enabled' => false,
        ], $disabledModuleDir);

        $themeManifest = ThemeManifest::fromArray([
            'slug' => 'custom',
            'name' => 'Custom',
            'description' => 'Custom theme',
            'active' => true,
            'enabled' => true,
        ], $activeThemeDir);

        $baseManifest = ThemeManifest::fromArray([
            'slug' => 'base',
            'name' => 'Base',
            'description' => 'Base theme',
            'enabled' => true,
            'active' => false,
        ], $baseThemeDir);

        $moduleRegistry = new ModuleRegistry([
            $moduleManifest->toArray(),
            $disabledModuleManifest->toArray(),
        ]);
        $themeRegistry = new ThemeRegistry([
            $themeManifest->toArray(),
            $baseManifest->toArray(),
        ]);

        $localeProvider = new LocaleProvider($this->workspace, $moduleRegistry, $themeRegistry);

        $translator = new Translator('en');
        $translator->setFallbackLocales(['en']);
        $manager = new CatalogueManager(
            $translator,
            $moduleRegistry,
            $themeRegistry,
            $localeProvider,
            $this->workspace,
            new ArrayAdapter(),
        );

        $manager->ensureLocale('en');
        $manager->ensureLocale($manager->getFallbackLocale());

        self::assertSame('Theme Foo', $translator->trans('foo', [], 'messages', 'en'));
        self::assertSame('Module Bar', $translator->trans('bar', [], 'messages', 'en'));
        self::assertSame('Base Baz', $translator->trans('baz', [], 'messages', 'en'));
        self::assertSame('System Qux', $translator->trans('qux', [], 'messages', 'en'));

        $manager->ensureLocale('de');
        $manager->ensureLocale($manager->getFallbackLocale());

        self::assertSame('Theme Foo DE', $translator->trans('foo', [], 'messages', 'de'));
        self::assertSame('Module Bar DE', $translator->trans('bar', [], 'messages', 'de'));
        self::assertSame('Base Baz DE', $translator->trans('baz', [], 'messages', 'de'));
        self::assertSame('System Qux DE', $translator->trans('qux', [], 'messages', 'de'));
        self::assertSame('Fallback EN', $translator->trans('fallbackOnly', [], 'messages', 'de'));
        self::assertSame('disabled', $translator->trans('disabled', [], 'messages', 'de'));
    }
}
