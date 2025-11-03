<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SetupSeedCommand;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class SetupSeedCommandTest extends KernelTestCase
{
    private string $projectDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->projectDir.'/var/setup');

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS app_user (
            id TEXT PRIMARY KEY,
            email TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            display_name TEXT NOT NULL,
            locale TEXT NOT NULL,
            timezone TEXT NOT NULL,
            status TEXT NOT NULL,
            flags TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            last_login_at TEXT
        )');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS app_user_role (
            user_id TEXT NOT NULL,
            role_name TEXT NOT NULL,
            assigned_at TEXT NOT NULL,
            assigned_by TEXT NULL
        )');
    }

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function tearDown(): void
    {
        if (self::$kernel !== null) {
            /** @var Connection $connection */
            $connection = self::getContainer()->get(Connection::class);
            $connection->executeStatement('DELETE FROM app_user WHERE email = :email', [
                'email' => 'admin@example.com',
            ]);
            $connection->executeStatement('DELETE FROM app_user_role WHERE user_id NOT IN (SELECT id FROM app_user)');
        }

        $this->filesystem->remove($this->projectDir.'/var/setup');
        parent::tearDown();
    }

    public function testSeedsAdminAccountFromPayload(): void
    {
        $payload = [
            'environment' => ['APP_ENV' => 'test', 'APP_DEBUG' => '1'],
            'storage' => ['root' => 'var/storage'],
            'admin' => [
                'email' => 'admin@example.com',
                'display_name' => 'Initial Admin',
                'password' => 'StrongPassword123!@#',
                'locale' => 'en',
                'timezone' => 'UTC',
                'require_mfa' => true,
                'recovery_email' => 'security@example.com',
            ],
            'settings' => [],
        ];

        $payloadPath = $this->projectDir.'/var/setup/runtime.json';
        file_put_contents($payloadPath, json_encode($payload, JSON_THROW_ON_ERROR));

        /** @var SetupSeedCommand $command */
        $command = self::getContainer()->get(SetupSeedCommand::class);
        $tester = new CommandTester($command);
        $tester->execute(['--payload' => $payloadPath]);

        $tester->assertCommandIsSuccessful();

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        $row = $connection->fetchAssociative('SELECT email, display_name FROM app_user WHERE email = :email', [
            'email' => 'admin@example.com',
        ]);

        self::assertNotFalse($row, 'Administrator should be created');
        self::assertSame('Initial Admin', $row['display_name']);
        self::assertFileDoesNotExist($payloadPath);
    }
}
