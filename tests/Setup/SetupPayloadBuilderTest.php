<?php

declare(strict_types=1);

namespace App\Tests\Setup;

use App\Setup\SetupConfiguration;
use App\Setup\SetupPayloadBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SetupPayloadBuilderTest extends TestCase
{
    private SetupConfiguration $configuration;
    private string $projectDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $this->configuration = new SetupConfiguration($stack);
        $this->configuration->rememberEnvironmentOverrides(['APP_ENV' => 'prod', 'APP_DEBUG' => '0']);
        $this->configuration->rememberStorageConfig(['root' => 'var/storage']);
        $this->configuration->rememberAdminAccount([
            'email' => 'admin@example.com',
            'display_name' => 'Admin',
            'password' => 'secret',
            'locale' => 'en',
            'timezone' => 'UTC',
            'require_mfa' => true,
            'recovery_email' => '',
        ]);

        $this->projectDir = sys_get_temp_dir().'/aavion_payload_'.bin2hex(random_bytes(4));
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testBuildCreatesPayloadFile(): void
    {
        $builder = new SetupPayloadBuilder($this->configuration, $this->projectDir, $this->filesystem);
        $path = $builder->build();

        self::assertFileExists($path);
        $json = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('prod', $json['environment']['APP_ENV']);
        self::assertSame('var/storage', $json['storage']['root']);
        self::assertSame('admin@example.com', $json['admin']['email']);
        self::assertArrayHasKey('projects', $json);
        self::assertIsArray($json['projects']);
    }

    public function testCleanupRemovesPayload(): void
    {
        $builder = new SetupPayloadBuilder($this->configuration, $this->projectDir, $this->filesystem);
        $path = $builder->build();
        self::assertFileExists($path);

        $builder->cleanup();
        self::assertFileDoesNotExist($path);
    }
}
