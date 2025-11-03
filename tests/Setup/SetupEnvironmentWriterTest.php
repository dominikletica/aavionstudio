<?php

declare(strict_types=1);

namespace App\Tests\Setup;

use App\Setup\SetupEnvironmentWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class SetupEnvironmentWriterTest extends TestCase
{
    private string $projectDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir().'/aavion_setup_env_writer_'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->projectDir);
        file_put_contents($this->projectDir.'/.env', "APP_ENV=dev\nAPP_DEBUG=1\nDATABASE_URL=sqlite:///%kernel.project_dir%/var/system.brain\n");
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testWriteCreatesEnvLocalWithOverrides(): void
    {
        $writer = new SetupEnvironmentWriter($this->projectDir, $this->filesystem);
        $writer->write([
            'APP_ENV' => 'prod',
            'APP_DEBUG' => '0',
            'APP_SECRET' => 'demo-secret',
        ], [
            'root' => 'var/storage',
        ]);

        $contents = file_get_contents($this->projectDir.'/.env.local');
        self::assertIsString($contents);
        self::assertStringContainsString('APP_ENV=prod', $contents);
        self::assertStringContainsString('APP_DEBUG=0', $contents);
        self::assertStringContainsString('APP_SECRET=demo-secret', $contents);
        self::assertStringContainsString('APP_STORAGE_ROOT=var/storage', $contents);
        self::assertStringContainsString('DATABASE_URL=sqlite:///%kernel.project_dir%/var/system.brain', $contents);
    }

    public function testWritePreservesExistingCustomKeys(): void
    {
        file_put_contents($this->projectDir.'/.env.local', "FOO=bar\nAPP_ENV=test\n");

        $writer = new SetupEnvironmentWriter($this->projectDir, $this->filesystem);
        $writer->write([], []);

        $contents = file_get_contents($this->projectDir.'/.env.local');
        self::assertIsString($contents);
        self::assertStringContainsString('FOO=bar', $contents);
        self::assertStringContainsString('APP_ENV=test', $contents);
    }
}
