<?php

declare(strict_types=1);

namespace App\Tests\Installer\Action;

use App\Installer\Action\ActionExecutor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

final class ActionExecutorTest extends KernelTestCase
{
    private string $projectDir;
    private Filesystem $filesystem;
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->projectDir = self::getContainer()->getParameter('kernel.project_dir');
        \assert(\is_string($this->projectDir));
        $this->filesystem = new Filesystem();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->temporaryFiles = [];
        $this->filesystem->remove($this->projectDir.'/var/test/extracted');

        parent::tearDown();
    }

    public function testConsoleStepForcesNonDebugMode(): void
    {
        $previous = getenv('APP_DEBUG');
        putenv('APP_DEBUG=1');

        $executor = self::getContainer()->get(ActionExecutor::class);

        $logs = [];
        $executor->execute(
            [
                ['type' => 'console', 'command' => ['about']],
            ],
            null,
            static function (string $channel, string $line = '') use (&$logs): void {
                if ($channel === 'log') {
                    $logs[] = $line;
                }
            }
        );

        $joined = implode("\n", $logs);
        self::assertStringContainsString('Debug                false', $joined, 'Console steps should run with APP_DEBUG=0 to reduce noisy output.');

        if ($previous === false) {
            putenv('APP_DEBUG');
        } else {
            putenv('APP_DEBUG='.$previous);
        }
    }

    public function testExtractUploadRejectsDirectoryTraversal(): void
    {
        $executor = self::getContainer()->get(ActionExecutor::class);
        $package = $this->createUploadedArchive([
            '../evil.txt' => 'intrusion',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/directory traversal/i');

        $executor->execute(
            [
                ['type' => 'extract_upload', 'target' => 'var/test/extracted'],
            ],
            $package,
            static function (): void {
            }
        );
    }

    public function testExtractUploadWritesFilesWithinTarget(): void
    {
        $executor = self::getContainer()->get(ActionExecutor::class);
        $package = $this->createUploadedArchive([
            'module/config.yaml' => 'key: value',
            'module/nested/file.txt' => 'hello-world',
        ]);

        $executor->execute(
            [
                ['type' => 'extract_upload', 'target' => 'var/test/extracted'],
            ],
            $package,
            static function (): void {
            }
        );

        $basePath = $this->projectDir.'/var/test/extracted/module';
        self::assertFileExists($basePath.'/config.yaml');
        self::assertSame('key: value', file_get_contents($basePath.'/config.yaml'));
        self::assertSame('hello-world', file_get_contents($basePath.'/nested/file.txt'));
    }

    private function createUploadedArchive(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pkg');
        if ($path === false) {
            throw new \RuntimeException('Failed to create temporary archive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create archive.');
        }

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, (string) $content);
        }

        $zip->close();

        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, 'package.zip', 'application/zip', null, true);
    }
}
