<?php

declare(strict_types=1);

namespace App\Tests\Setup;

use App\Installer\DefaultSystemSettings;
use App\Setup\SetupConfigurator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SetupConfiguratorTest extends KernelTestCase
{
    private Connection $connection;
    private SetupConfigurator $configurator;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->configurator = $container->get(SetupConfigurator::class);

        $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS app_system_setting (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            type TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');

        $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS app_project (
            id TEXT PRIMARY KEY,
            slug TEXT NOT NULL,
            name TEXT NOT NULL,
            locale TEXT NOT NULL,
            timezone TEXT NOT NULL,
            settings TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');

        $this->connection->executeStatement('DELETE FROM app_system_setting');
        $this->connection->executeStatement('DELETE FROM app_project');
    }

    protected function tearDown(): void
    {
        if (self::$kernel !== null) {
            $this->connection->executeStatement('DELETE FROM app_system_setting');
            $this->connection->executeStatement('DELETE FROM app_project');
        }

        parent::tearDown();
    }

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testApplyFromDataPersistsSettingsAndProjects(): void
    {
        $settings = DefaultSystemSettings::all();
        $settings['core.instance_name'] = 'Configurator Test';

        $projects = [
            [
                'slug' => 'demo',
                'name' => 'Demo Project',
                'locale' => 'en',
                'timezone' => 'UTC',
                'settings' => [
                    'description' => 'Demo project',
                    'navigation' => [
                        'auto_include' => true,
                    ],
                    'errors' => [],
                ],
            ],
        ];

        $this->configurator->applyFromData($settings, $projects);

        $storedSetting = $this->connection->fetchAssociative('SELECT value FROM app_system_setting WHERE key = :key', [
            'key' => 'core.instance_name',
        ]);
        self::assertNotFalse($storedSetting);
        self::assertSame('"Configurator Test"', $storedSetting['value']);

        $storedProject = $this->connection->fetchAssociative('SELECT slug, name FROM app_project WHERE slug = :slug', [
            'slug' => 'demo',
        ]);
        self::assertNotFalse($storedProject);
        self::assertSame('Demo Project', $storedProject['name']);
    }
}
