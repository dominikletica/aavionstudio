<?php

declare(strict_types=1);

namespace App\Tests\Bootstrap;

use App\Bootstrap\RootEntryPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class RootEntryPointTest extends TestCase
{
    private string $projectDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir().'/aavion_root_entry_'.uniqid('', true);
        $this->filesystem->mkdir($this->projectDir.'/public');
        $this->filesystem->dumpFile($this->projectDir.'/public/index.php', "<?php\nreturn true;\n");
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
        parent::tearDown();
    }

    public function testPrepareNormalisesRouteAndFlags(): void
    {
        $server = [
            'SCRIPT_FILENAME' => $this->projectDir.'/index.php',
            'REQUEST_URI' => '/index.php?route=/setup&foo=bar',
        ];
        $get = [
            'route' => '/setup',
            'foo' => 'bar',
        ];
        $env = [];

        $context = RootEntryPoint::prepare($this->projectDir, $server, $get, $env);

        self::assertSame($this->projectDir.'/public/index.php', $context->frontController);
        self::assertSame('/setup', $context->route);
        self::assertSame('foo=bar', $context->queryString);
        self::assertTrue($context->compatibilityMode);
        self::assertFalse($context->forced);

        self::assertSame('/setup?foo=bar', $server['REQUEST_URI']);
        self::assertSame('/setup', $server['PATH_INFO']);
        self::assertSame('foo=bar', $server['QUERY_STRING']);
        self::assertSame('1', $server[RootEntryPoint::FLAG_ROOT_ENTRY]);
        self::assertSame('0', $server[RootEntryPoint::FLAG_FORCED]);
        self::assertSame('/setup', $server[RootEntryPoint::FLAG_ROUTE]);
        self::assertSame('/setup?foo=bar', $server[RootEntryPoint::FLAG_REQUEST_URI]);
        self::assertSame($this->projectDir.'/public/index.php', $server['SCRIPT_FILENAME']);

        self::assertSame('/setup?foo=bar', $context->requestUri());

        self::assertArrayNotHasKey('route', $get);
        self::assertSame('bar', $get['foo']);

        self::assertSame('1', $env[RootEntryPoint::FLAG_ROOT_ENTRY]);
        self::assertSame('0', $env[RootEntryPoint::FLAG_FORCED]);
        self::assertSame('/setup', $env[RootEntryPoint::FLAG_ROUTE]);
    }

    public function testForcedModeSetsFlag(): void
    {
        $server = [
            'SCRIPT_FILENAME' => $this->projectDir.'/index.php',
            'REQUEST_URI' => '/index.php',
        ];
        $get = [];
        $env = [
            'APP_FORCE_ROOT_ENTRY' => '1',
        ];

        $context = RootEntryPoint::prepare($this->projectDir, $server, $get, $env);

        self::assertTrue($context->forced);
        self::assertSame('1', $server[RootEntryPoint::FLAG_FORCED]);
        self::assertSame('1', $env[RootEntryPoint::FLAG_FORCED]);
    }
}
