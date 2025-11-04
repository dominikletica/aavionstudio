<?php

declare(strict_types=1);

namespace App\Tests\Setup;

use App\Setup\SetupHelpLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class SetupHelpLoaderTest extends TestCase
{
    private string $projectDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir().'/aavion_help_'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->projectDir.'/docs/setup');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testReturnsEmptyArrayWhenNoHelpFileFound(): void
    {
        $loader = new SetupHelpLoader($this->projectDir, $this->filesystem);
        self::assertSame([], $loader->load('en'));
    }

    public function testLoadGroupsEntriesBySection(): void
    {
        $entries = [
            ['section' => 'setup.environment', 'type' => 'inline_help', 'title' => 'Foo', 'body' => 'Bar'],
            ['section' => 'setup.environment', 'type' => 'tooltip', 'title' => 'Baz', 'body' => 'Qux'],
            ['section' => 'setup.storage', 'type' => 'inline_help', 'title' => 'Storage', 'body' => 'Info'],
        ];

        $this->filesystem->dumpFile(
            $this->projectDir.'/docs/setup/help.json',
            json_encode($entries, JSON_THROW_ON_ERROR)
        );

        $loader = new SetupHelpLoader($this->projectDir, $this->filesystem);
        $grouped = $loader->load('en');

        self::assertArrayHasKey('setup.environment', $grouped);
        self::assertCount(2, $grouped['setup.environment']);
        self::assertArrayHasKey('setup.storage', $grouped);
        self::assertCount(1, $grouped['setup.storage']);
    }

    public function testLocaleSpecificEntriesExtendFallback(): void
    {
        $this->filesystem->dumpFile(
            $this->projectDir.'/docs/setup/help.json',
            json_encode([
                ['section' => 'setup.general', 'type' => 'inline_help', 'title' => 'Default', 'body' => 'Fallback']
            ], JSON_THROW_ON_ERROR)
        );

        $this->filesystem->dumpFile(
            $this->projectDir.'/docs/setup/help.de.json',
            json_encode([
                ['section' => 'setup.general', 'type' => 'inline_help', 'title' => 'Deutsch', 'body' => 'Inhalt']
            ], JSON_THROW_ON_ERROR)
        );

        $loader = new SetupHelpLoader($this->projectDir, $this->filesystem);
        $grouped = $loader->load('de');

        self::assertCount(2, $grouped['setup.general']);
        self::assertSame('Default', $grouped['setup.general'][0]['title']);
        self::assertSame('Deutsch', $grouped['setup.general'][1]['title']);
    }

    public function testTooltipTargetsArePreserved(): void
    {
        $this->filesystem->dumpFile(
            $this->projectDir.'/docs/setup/help.json',
            json_encode([
                [
                    'section' => 'setup.environment',
                    'type' => 'tooltip',
                    'title' => 'Secret help',
                    'body' => 'Body',
                    'target' => 'environment.secret',
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $loader = new SetupHelpLoader($this->projectDir, $this->filesystem);
        $entries = $loader->load('en');

        self::assertSame('environment.secret', $entries['setup.environment'][0]['target']);
    }
}
