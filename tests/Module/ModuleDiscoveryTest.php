<?php

declare(strict_types=1);

namespace App\Tests\Module;

use App\Module\ModuleDiscovery;
use App\Module\ModuleManifest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ModuleDiscoveryTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/aavion_modules_'.uniqid('', true);
        (new Filesystem())->mkdir($this->workspace);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workspace);

        parent::tearDown();
    }

    public function testDiscoverLoadsModuleManifestFromFilesystem(): void
    {
        $moduleDir = $this->workspace.'/demo';
        (new Filesystem())->mkdir($moduleDir);

        $manifestFile = $moduleDir.'/module.php';

        file_put_contents($manifestFile, <<<'PHP'
<?php

declare(strict_types=1);

use App\Module\ModuleManifest;

return [
    'slug' => 'demo',
    'name' => 'Demo Module',
    'description' => 'Demo module used for discovery testing.',
    'services' => 'config/services.php',
    'priority' => 10,
    'repository' => 'https://example.com/demo-module.git',
];
PHP);

        $discovery = new ModuleDiscovery($this->workspace);
        $manifests = $discovery->discover();

        self::assertCount(1, $manifests);
        self::assertInstanceOf(ModuleManifest::class, $manifests[0]);
        self::assertSame('demo', $manifests[0]->slug);
        self::assertSame('Demo Module', $manifests[0]->name);
        self::assertSame(10, $manifests[0]->priority);
        self::assertStringEndsWith('/config/services.php', $manifests[0]->servicesPath() ?? '');
        self::assertSame('https://example.com/demo-module.git', $manifests[0]->repository);
    }
}
