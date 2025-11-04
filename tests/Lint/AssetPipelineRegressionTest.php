<?php

declare(strict_types=1);

namespace App\Tests\Lint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class AssetPipelineRegressionTest extends TestCase
{
    private const PROCESS_TIMEOUT = 180;

    private string $projectDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = dirname(__DIR__, 2);
        $this->filesystem = new Filesystem();

        $this->resetProjectState();
    }

    public function testRebuildPurgesPreviouslySyncedAssets(): void
    {
        $modulesRoot = $this->projectDir.'/assets/modules';
        $themesRoot = $this->projectDir.'/assets/themes';

        $legacyModule = $modulesRoot.'/legacy-module';
        $legacyTheme = $themesRoot.'/legacy-theme';

        $this->filesystem->mkdir([$legacyModule, $legacyTheme]);
        file_put_contents($legacyModule.'/obsolete.txt', 'remove-me');
        file_put_contents($legacyTheme.'/obsolete.txt', 'remove-me');

        $this->runConsoleCommand(['app:assets:rebuild', '--force'], 'Asset rebuild with legacy mirrors');

        self::assertDirectoryDoesNotExist(
            $legacyModule,
            'Legacy module assets should be purged before syncing fresh mirrors.',
        );
        self::assertDirectoryDoesNotExist(
            $legacyTheme,
            'Legacy theme assets should be purged before syncing fresh mirrors.',
        );

        self::assertDirectoryExists($modulesRoot, 'Module asset mirror root should be recreated.');
        self::assertDirectoryExists($themesRoot, 'Theme asset mirror root should be recreated.');
    }

    public function testSyncCommandRefreshesContainerForNewModule(): void
    {
        $moduleSlug = 'regression-sync-module';
        $modulesRoot = $this->projectDir.'/modules';
        $moduleDir = $modulesRoot.'/'.$moduleSlug;

        $this->clearDirectory($moduleDir, true);
        $this->clearDirectory($this->projectDir.'/assets/modules/'.$moduleSlug, true);

        $this->runConsoleCommand(['cache:warmup'], 'Baseline warmup without regression module');

        $this->filesystem->mkdir($moduleDir.'/assets');
        $moduleDefinition = <<<'PHP'
<?php

return [
    'slug' => 'regression-sync-module',
    'name' => 'Regression Sync Module',
    'description' => 'Ensures app:assets:sync refreshes its container.',
];
PHP;
        file_put_contents($moduleDir.'/module.php', $moduleDefinition);
        file_put_contents($moduleDir.'/assets/example.txt', 'regression');

        $this->runConsoleCommand(['app:assets:sync'], 'Direct sync should refresh cache for new module');

        $mirrored = $this->projectDir.'/assets/modules/'.$moduleSlug.'/example.txt';
        self::assertFileExists(
            $mirrored,
            'Newly introduced module assets should be mirrored without a manual cache clear.',
        );

        $this->clearDirectory($moduleDir, true);
        $this->clearDirectory(dirname($mirrored), true);
    }

    private function resetProjectState(): void
    {
        $this->clearDirectory($this->projectDir.'/public/assets', true);
        $this->clearDirectory($this->projectDir.'/var/cache');
        $this->clearDirectory($this->projectDir.'/var/log');
        $this->clearDirectory($this->projectDir.'/modules/regression-sync-module', true);
        $this->clearDirectory($this->projectDir.'/assets/modules/regression-sync-module', true);
    }

    private function clearDirectory(string $path, bool $removeRoot = false): void
    {
        if (!file_exists($path)) {
            if (!$removeRoot) {
                $this->filesystem->mkdir($path);
            }

            return;
        }

        if (is_file($path) || is_link($path)) {
            $this->filesystem->remove($path);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $pathname = $file->getPathname();

            if ($file->isDir()) {
                @rmdir($pathname);
            } else {
                $this->filesystem->remove($pathname);
            }
        }

        if ($removeRoot) {
            @rmdir($path);
        }
    }

    /**
     * @param list<string> $arguments
     */
    private function runConsoleCommand(array $arguments, string $label): void
    {
        $command = array_merge(['php', 'bin/console'], $arguments, ['--env=test', '--no-debug', '--quiet']);

        $process = new Process($command, $this->projectDir, [
            'APP_ENV' => 'test',
            'APP_DEBUG' => '0',
        ]);
        $process->setTimeout(self::PROCESS_TIMEOUT);
        $process->run();

        $output = trim($process->getOutput().$process->getErrorOutput());

        $this->assertSame(
            0,
            $process->getExitCode(),
            sprintf(
                "[asset pipeline regression] %s failed with exit code %d.\nCommand: %s\nOutput:\n%s",
                $label,
                $process->getExitCode(),
                $process->getCommandLine(),
                $output === '' ? '(no output)' : $output,
            ),
        );
    }
}
