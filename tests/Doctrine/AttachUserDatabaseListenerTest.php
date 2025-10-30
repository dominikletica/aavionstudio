<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Doctrine\Health\SqliteHealthChecker;
use App\Doctrine\Listener\AttachUserDatabaseListener;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class AttachUserDatabaseListenerTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/aavionstudio_'.uniqid('', true);
        (new Filesystem())->mkdir($this->workspace);
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->workspace);

        parent::tearDown();
    }

    public function testItAttachesSecondaryDatabaseAndConfiguresPragmas(): void
    {
        $filesystem = new Filesystem();
        $primaryPath = $this->workspace.'/system.brain';
        $secondaryPath = $this->workspace.'/user.brain';

        $connection = $this->createSqliteConnection($primaryPath);

        $listener = new AttachUserDatabaseListener(
            userDatabasePath: $secondaryPath,
            filesystem: $filesystem,
            logger: null,
            busyTimeoutMs: 7000,
        );

        $listener->postConnect(new ConnectionEventArgs($connection));

        $databases = $connection->fetchAllAssociative('PRAGMA database_list');
        self::assertContains('user_brain', array_column($databases, 'name'));

        $busyTimeout = (int) $connection->fetchOne('PRAGMA busy_timeout');
        self::assertSame(7000, $busyTimeout);

        $foreignKeys = (int) $connection->fetchOne('PRAGMA foreign_keys');
        self::assertSame(1, $foreignKeys);

        self::assertFileExists($secondaryPath, 'Secondary database file should be created on demand.');

        $healthReport = (new SqliteHealthChecker($connection, $secondaryPath))->check();
        self::assertTrue($healthReport->secondaryAttached, 'user_brain should remain attached after listener execution.');
        self::assertSame(7000, $healthReport->busyTimeoutMs);

        $connection->close();
    }

    private function createSqliteConnection(string $path): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $path,
        ]);
    }
}
