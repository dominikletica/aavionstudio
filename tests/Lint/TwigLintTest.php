<?php

declare(strict_types=1);

namespace App\Tests\Lint;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class TwigLintTest extends TestCase
{
    private const PROCESS_TIMEOUT = 60;

    /**
     * @return iterable<string, array{string}>
     */
    public static function twigDirectoriesProvider(): iterable
    {
        $projectDir = dirname(__DIR__, 2);

        $paths = ['templates'];

        $themeTemplateDirs = glob($projectDir.'/themes/*/templates', \GLOB_ONLYDIR) ?: [];
        foreach ($themeTemplateDirs as $path) {
            $relative = substr($path, \strlen($projectDir) + 1);
            $paths[] = ltrim(str_replace('\\', '/', $relative), '/');
        }

        $moduleTemplateDirs = glob($projectDir.'/modules/*/templates', \GLOB_ONLYDIR) ?: [];
        foreach ($moduleTemplateDirs as $path) {
            $relative = substr($path, \strlen($projectDir) + 1);
            $paths[] = ltrim(str_replace('\\', '/', $relative), '/');
        }

        $paths = array_values(array_unique($paths));

        foreach ($paths as $path) {
            yield $path => [$path];
        }
    }

    #[DataProvider('twigDirectoriesProvider')]
    public function testTwigTemplatesLint(string $relativePath): void
    {
        $projectDir = dirname(__DIR__, 2);
        $absolutePath = $projectDir.'/'.$relativePath;

        $command = [
            'php',
            'bin/console',
            'lint:twig',
            $absolutePath,
            '--env=test',
            '--no-debug',
            '--quiet',
        ];

        $process = new Process($command, $projectDir, [
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
                "[twig lint] Lint failed for '%s' with exit code %d.\nCommand: %s\nOutput:\n%s",
                $relativePath,
                $process->getExitCode(),
                $process->getCommandLine(),
                $output === '' ? '(no output)' : $output,
            ),
        );
    }
}
