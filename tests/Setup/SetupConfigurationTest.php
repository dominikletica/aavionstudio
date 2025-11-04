<?php

declare(strict_types=1);

namespace App\Tests\Setup;

use App\Setup\SetupConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SetupConfigurationTest extends TestCase
{
    public function testHasEnvironmentOverridesReflectsRememberedData(): void
    {
        $configuration = $this->createConfiguration();

        self::assertFalse($configuration->hasEnvironmentOverrides());

        $configuration->rememberEnvironmentOverrides([
            'APP_ENV' => 'prod',
            'APP_DEBUG' => '0',
        ]);

        self::assertTrue($configuration->hasEnvironmentOverrides());
        self::assertSame('prod', $configuration->getEnvironmentOverrides()['APP_ENV']);
        self::assertSame('0', $configuration->getEnvironmentOverrides()['APP_DEBUG']);
    }

    public function testHasStorageConfigTracksRootPath(): void
    {
        $configuration = $this->createConfiguration();

        self::assertFalse($configuration->hasStorageConfig());

        $configuration->rememberStorageConfig(['root' => '/srv/storage']);

        self::assertTrue($configuration->hasStorageConfig());
        self::assertSame('/srv/storage', $configuration->getStorageConfig()['root']);
    }

    public function testHasAdminAccountRequiresEmailAndPassword(): void
    {
        $configuration = $this->createConfiguration();

        self::assertFalse($configuration->hasAdminAccount());

        $configuration->rememberAdminAccount([
            'email' => 'admin@example.com',
            'display_name' => 'Admin',
            'password' => 'SecretPassword123!',
            'locale' => 'en',
            'timezone' => 'UTC',
            'require_mfa' => true,
        ]);

        self::assertTrue($configuration->hasAdminAccount());
        $admin = $configuration->getAdminAccount();
        self::assertSame('admin@example.com', $admin['email']);
        self::assertSame('SecretPassword123!', $admin['password']);
        self::assertTrue($admin['require_mfa']);
    }

    public function testFreezeDetachesSessionDataForStreaming(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();

        $request = new Request();
        $request->setSession($session);

        $stack = new RequestStack();
        $stack->push($request);

        $configuration = new SetupConfiguration($stack);
        $configuration->rememberEnvironmentOverrides([
            'APP_ENV' => 'prod',
            'APP_DEBUG' => '0',
        ]);
        $configuration->rememberAdminAccount([
            'email' => 'freeze@example.com',
            'display_name' => 'Frozen Admin',
            'password' => 'FrozenPass123!',
            'locale' => 'en',
            'timezone' => 'UTC',
            'require_mfa' => false,
        ]);

        self::assertNotNull($session->get('_app.setup.configuration'));

        $configuration->freeze();

        self::assertNull($session->get('_app.setup.configuration'), 'Freeze should remove payload from the persisted session.');
        self::assertSame('prod', $configuration->getEnvironmentOverrides()['APP_ENV']);
        self::assertSame('freeze@example.com', $configuration->getAdminAccount()['email']);

        $configuration->clear();

        self::assertFalse($configuration->hasEnvironmentOverrides(), 'Clearing after freeze should drop the snapshot.');
        self::assertFalse($configuration->hasAdminAccount());
    }

    private function createConfiguration(): SetupConfiguration
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();

        $request = new Request();
        $request->setSession($session);

        $stack = new RequestStack();
        $stack->push($request);

        return new SetupConfiguration($stack);
    }
}
