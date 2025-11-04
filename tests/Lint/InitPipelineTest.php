<?php

declare(strict_types=1);

namespace App\Tests\Lint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class InitPipelineTest extends TestCase
{
    private const PROCESS_TIMEOUT = 180;

    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = dirname(__DIR__, 2);
    }

    public function testInitPipelineCommandsSucceed(): void
    {
        $this->resetProjectState();

        $this->runConsoleCommand(['app:assets:rebuild', '--force'], 'Asset rebuild');
        $this->runConsoleCommand(['doctrine:database:create'], 'Doctrine database create');
        $this->runConsoleCommand(['messenger:setup-transports', '--no-interaction'], 'Messenger transports setup');

        self::assertDirectoryExists($this->projectDir.'/public/assets', 'Compiled assets directory missing.');
        self::assertFileExists($this->projectDir.'/var/test/system.brain', 'SQLite database not created.');
    }

    private function resetProjectState(): void
    {
        $this->clearDirectory($this->projectDir.'/public/assets', true);

        foreach (glob($this->projectDir.'/var/test/*.brain*') ?: [] as $databaseFile) {
            if (is_file($databaseFile)) {
                @unlink($databaseFile);
            }
        }

        $this->clearDirectory($this->projectDir.'/var/cache');
        $this->clearDirectory($this->projectDir.'/var/log');
        $this->clearDirectory($this->projectDir.'/var/test', false);
    }

    private function clearDirectory(string $path, bool $removeRoot = false): void
    {
        if (!file_exists($path)) {
            if (!$removeRoot) {
                if (!mkdir($path, 0777, true) && !is_dir($path)) {
                    throw new \RuntimeException(sprintf('Unable to create directory "%s"', $path));
                }
            }

            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);

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
                @unlink($pathname);
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

        $process = new Process(
            $command,
            $this->projectDir,
            [
                'APP_ENV' => 'test',
                'APP_DEBUG' => '0',
            ],
        );
        $process->setTimeout(self::PROCESS_TIMEOUT);
        $process->run();

        $output = trim($process->getOutput().$process->getErrorOutput());

        $this->assertSame(
            0,
            $process->getExitCode(),
            sprintf(
                "[init pipeline] %s failed with exit code %d.\nCommand: %s\nOutput:\n%s",
                $label,
                $process->getExitCode(),
                $process->getCommandLine(),
                $output === '' ? '(no output)' : $output,
            ),
        );
    }
}
