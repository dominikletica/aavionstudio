<?php

declare(strict_types=1);

namespace App\Tests\Installer\Action;

use App\Installer\Action\ActionExecutor;
use App\Setup\SetupConfiguration;
use App\Setup\SetupState;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class InstallerPipelineTest extends KernelTestCase
{
    private string $projectDir;
    private string $envLocalPath;
    private string $payloadPath;
    private Filesystem $filesystem;

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
        $envLocalPath = self::getContainer()->getParameter('app.setup.env_local_path');
        \assert(\is_string($envLocalPath));
        $this->envLocalPath = $envLocalPath;
        $payloadPath = self::getContainer()->getParameter('app.setup.payload_path');
        \assert(\is_string($payloadPath));
        $this->payloadPath = $payloadPath;

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get(RequestStack::class);
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        /** @var SetupConfiguration $configuration */
        $configuration = self::getContainer()->get(SetupConfiguration::class);
        $configuration->rememberEnvironmentOverrides([
            'APP_ENV' => 'test',
            'APP_DEBUG' => '1',
            'APP_SECRET' => 'integration-secret',
        ]);
        $configuration->rememberStorageConfig(['root' => 'var/integration-storage']);
        $configuration->rememberAdminAccount([
            'email' => 'pipeline@example.com',
            'display_name' => 'Pipeline Admin',
            'password' => 'IntegrationPass123!',
            'locale' => 'en',
            'timezone' => 'UTC',
            'require_mfa' => true,
        ]);
        $configuration->rememberSystemSettings([
            'core.instance_name' => 'Pipeline Instance',
            'core.locale' => 'en',
            'core.timezone' => 'UTC',
        ]);
        $configuration->rememberProjects([
            [
                'slug' => 'default',
                'name' => 'Default Project',
                'locale' => 'en',
                'timezone' => 'UTC',
                'settings' => [
                    'description' => 'Default integration project',
                    'errors' => [],
                ],
            ],
        ]);

        $payloadDir = \dirname($this->payloadPath);
        $this->filesystem->mkdir($payloadDir);

        $this->filesystem->dumpFile($this->payloadPath, json_encode([
            'storage' => [
                'root' => 'var/integration-storage',
            ],
            'admin' => [
                'email' => 'pipeline@example.com',
                'display_name' => 'Pipeline Admin',
                'password' => 'IntegrationPass123!',
                'locale' => 'en',
                'timezone' => 'UTC',
                'require_mfa' => true,
            ],
            'settings' => [
                'core.instance_name' => 'Pipeline Instance',
                'core.locale' => 'en',
                'core.timezone' => 'UTC',
            ],
            'projects' => [
                [
                    'slug' => 'default',
                    'name' => 'Default Project',
                    'locale' => 'en',
                    'timezone' => 'UTC',
                    'settings' => [
                        'description' => 'Default integration project',
                        'errors' => [],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS app_system_setting (key TEXT PRIMARY KEY, value TEXT NOT NULL, type TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS user_brain.app_project (id TEXT PRIMARY KEY, slug TEXT NOT NULL, name TEXT NOT NULL, locale TEXT NOT NULL, timezone TEXT NOT NULL, settings TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
        $connection->executeStatement('DELETE FROM app_system_setting');
        $connection->executeStatement('DELETE FROM user_brain.app_project');
        $connection->executeStatement('DELETE FROM app_user WHERE email = :email', ['email' => 'pipeline@example.com']);
        $connection->executeStatement('DELETE FROM app_user_role WHERE user_id NOT IN (SELECT id FROM app_user)');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove([
            $this->payloadPath,
            \dirname($this->payloadPath),
            $this->projectDir.'/var/log/setup',
            $this->projectDir.'/var/integration-storage',
        ]);
        $this->filesystem->remove($this->envLocalPath);
        /** @var SetupState $setupState */
        $setupState = self::getContainer()->get(SetupState::class);
        $this->filesystem->remove($setupState->lockFilePath());

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM app_system_setting');
        $connection->executeStatement('DELETE FROM user_brain.app_project');
        $connection->executeStatement('DELETE FROM app_user WHERE email = :email', ['email' => 'pipeline@example.com']);

        parent::tearDown();
    }

    public function testFullInstallerPipeline(): void
    {
        /** @var ActionExecutor $executor */
        $executor = self::getContainer()->get(ActionExecutor::class);

        $executor->execute(
            [
                ['type' => 'write_env'],
                ['type' => 'prepare_payload'],
                ['type' => 'configure'],
                ['type' => 'lock'],
            ],
            null,
            static function (string $type, string $message = '', array $extra = []): void {
            }
        );

        self::assertFileExists($this->envLocalPath);
        $envContents = (string) file_get_contents($this->envLocalPath);
        self::assertStringContainsString('APP_ENV=test', $envContents);
        self::assertStringContainsString('APP_SECRET=integration-secret', $envContents);
        self::assertStringContainsString('APP_STORAGE_ROOT=var/integration-storage', $envContents);
        self::assertStringContainsString('DATABASE_URL=sqlite:///%kernel.project_dir%/var/integration-storage/databases/system.brain', $envContents);

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        $setting = $connection->fetchAssociative('SELECT value FROM app_system_setting WHERE key = :key', ['key' => 'core.instance_name']);
        self::assertNotFalse($setting);
        self::assertSame('"Pipeline Instance"', $setting['value']);

        $entry = $connection->fetchAssociative('SELECT name FROM user_brain.app_project WHERE slug = :slug', ['slug' => 'default']);
        self::assertNotFalse($entry);
        self::assertSame('Default Project', $entry['name']);

        /** @var SetupState $setupState */
        $setupState = self::getContainer()->get(SetupState::class);
        self::assertFileExists($setupState->lockFilePath());
    }
}
