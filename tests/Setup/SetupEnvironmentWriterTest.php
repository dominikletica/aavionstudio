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
    private string $envPath;
    private string $envLocalPath;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir().'/aavion_setup_env_writer_'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->projectDir);
        $this->envPath = $this->projectDir.'/.env';
        $this->envLocalPath = $this->projectDir.'/.env.local';

        file_put_contents($this->envPath, <<<ENV
APP_ENV=dev
APP_DEBUG=1
DATABASE_URL=sqlite:///%kernel.project_dir%/var/system.brain
MESSENGER_TRANSPORT_DSN=doctrine://default
MAILER_DSN=null://localhost
LOCK_DSN=flock
ENV);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testWriteCreatesEnvLocalWithOverrides(): void
    {
        $writer = new SetupEnvironmentWriter(
            $this->filesystem,
            $this->envPath,
            $this->envLocalPath,
            $this->projectDir,
        );
        $writer->write([
            'APP_ENV' => 'prod',
            'APP_DEBUG' => '0',
            'APP_SECRET' => 'demo-secret',
        ], [
            'root' => 'var/storage',
        ]);

        $contents = file_get_contents($this->envLocalPath);
        self::assertIsString($contents);
        self::assertStringContainsString('APP_ENV=prod', $contents);
        self::assertStringContainsString('APP_DEBUG=0', $contents);
        self::assertStringContainsString('APP_SECRET=demo-secret', $contents);
        self::assertStringContainsString('APP_STORAGE_ROOT=var/storage', $contents);
        self::assertStringContainsString('DATABASE_URL=sqlite:///%kernel.project_dir%/var/storage/databases/system.brain', $contents);
        self::assertStringContainsString('MESSENGER_TRANSPORT_DSN=doctrine://default', $contents);
        self::assertDirectoryExists($this->projectDir.'/var/storage/databases');
        self::assertDirectoryExists($this->projectDir.'/var/storage/uploads');
    }

    public function testWritePreservesExistingCustomKeys(): void
    {
        file_put_contents($this->envLocalPath, "FOO=bar\nAPP_ENV=test\n");

        $writer = new SetupEnvironmentWriter(
            $this->filesystem,
            $this->envPath,
            $this->envLocalPath,
            $this->projectDir,
        );
        $writer->write([], []);

        $contents = file_get_contents($this->envLocalPath);
        self::assertIsString($contents);
        self::assertStringContainsString('FOO=bar', $contents);
        self::assertStringContainsString('APP_ENV=test', $contents);
        self::assertStringContainsString('DATABASE_URL=sqlite:///%kernel.project_dir%/var/storage/databases/system.brain', $contents);
    }
}
