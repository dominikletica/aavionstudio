<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Doctrine\Health\SqliteHealthChecker;
use App\Doctrine\Health\SqliteHealthReport;
use App\Doctrine\Middleware\AttachUserDatabaseMiddleware;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class SqliteHealthCheckerTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/aavionstudio_health_'.uniqid('', true);
        (new Filesystem())->mkdir($this->workspace);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workspace);

        parent::tearDown();
    }

    public function testHealthReportProvidesPathsAndFlags(): void
    {
        $primaryPath = $this->workspace.'/system.brain';
        $secondaryPath = $this->workspace.'/user.brain';

        $config = new Configuration();
        $config->setMiddlewares([
            new AttachUserDatabaseMiddleware($secondaryPath, new Filesystem(), null, 3000),
        ]);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $primaryPath,
        ], $config);

        $healthChecker = new SqliteHealthChecker($connection, $secondaryPath);
        $report = $healthChecker->check();

        self::assertInstanceOf(SqliteHealthReport::class, $report);
        self::assertSame($primaryPath, $report->primaryPath);
        self::assertSame($secondaryPath, $report->secondaryPath);
        self::assertTrue($report->secondaryAttached);
        self::assertGreaterThan(0, $report->busyTimeoutMs);

        $asArray = $report->toArray();
        self::assertArrayHasKey('primary_path', $asArray);
        self::assertArrayHasKey('secondary_attached', $asArray);

        $connection->close();
    }
}
