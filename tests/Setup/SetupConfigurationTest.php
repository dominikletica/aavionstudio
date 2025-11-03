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
    private SetupConfiguration $configuration;

    protected function setUp(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $stack = new RequestStack();
        $stack->push($request);

        $this->configuration = new SetupConfiguration($stack);
    }

    public function testEnvironmentOverridesPersistAsStrings(): void
    {
        $this->configuration->rememberEnvironmentOverrides([
            'APP_ENV' => 'prod',
            'APP_DEBUG' => 0,
            'APP_SECRET' => 'abc123',
            'UNUSED' => 'ignored',
        ]);

        self::assertSame([
            'APP_ENV' => 'prod',
            'APP_DEBUG' => '0',
            'APP_SECRET' => 'abc123',
        ], $this->configuration->getEnvironmentOverrides());
    }

    public function testStorageConfigReturnsDefaultRootWhenUnset(): void
    {
        self::assertSame('var/storage', $this->configuration->getStorageConfig()['root']);
    }

    public function testStorageConfigPersistsCustomRoot(): void
    {
        $this->configuration->rememberStorageConfig(['root' => '/mnt/data']);

        self::assertSame('/mnt/data', $this->configuration->getStorageConfig()['root']);
    }

    public function testAdminAccountPersistsAndNormalisesData(): void
    {
        $this->configuration->rememberAdminAccount([
            'email' => 'admin@example.com',
            'display_name' => 'Admin',
            'password' => 'secret',
            'locale' => 'en_GB',
            'timezone' => 'Europe/London',
            'require_mfa' => '1',
            'recovery_email' => 'security@example.com',
            'recovery_phone' => '+4912345',
        ]);

        $admin = $this->configuration->getAdminAccount();

        self::assertSame('admin@example.com', $admin['email']);
        self::assertSame('Admin', $admin['display_name']);
        self::assertSame('secret', $admin['password']);
        self::assertSame('en_GB', $admin['locale']);
        self::assertSame('Europe/London', $admin['timezone']);
        self::assertTrue($admin['require_mfa']);
        self::assertSame('security@example.com', $admin['recovery_email']);
        self::assertSame('+4912345', $admin['recovery_phone']);
    }

    public function testClearRemovesSessionData(): void
    {
        $this->configuration->rememberEnvironmentOverrides(['APP_ENV' => 'prod']);
        $this->configuration->rememberStorageConfig(['root' => '/tmp']);
        $this->configuration->rememberAdminAccount([
            'email' => 'admin@example.com',
            'display_name' => 'Admin',
            'password' => 'secret',
            'locale' => 'en',
            'timezone' => 'UTC',
            'require_mfa' => false,
            'recovery_email' => '',
        ]);

        $this->configuration->clear();

        $defaultEnv = (string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev');
        self::assertSame($defaultEnv, $this->configuration->getEnvironmentOverrides()['APP_ENV']);
        self::assertSame('var/storage', $this->configuration->getStorageConfig()['root']);
        self::assertSame('', $this->configuration->getAdminAccount()['email']);
    }
}
