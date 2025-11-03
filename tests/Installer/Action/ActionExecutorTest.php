<?php

declare(strict_types=1);

namespace App\Tests\Installer\Action;

use App\Installer\Action\ActionExecutor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ActionExecutorTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testConsoleStepForcesNonDebugMode(): void
    {
        $previous = getenv('APP_DEBUG');
        putenv('APP_DEBUG=1');

        self::bootKernel();
        $executor = self::getContainer()->get(ActionExecutor::class);

        $logs = [];
        $executor->execute(
            [
                ['type' => 'console', 'command' => ['about']],
            ],
            null,
            static function (string $channel, string $line = '') use (&$logs): void {
                if ($channel === 'log') {
                    $logs[] = $line;
                }
            }
        );

        $joined = implode("\n", $logs);
        self::assertStringContainsString('Debug                false', $joined, 'Console steps should run with APP_DEBUG=0 to reduce noisy output.');

        if ($previous === false) {
            putenv('APP_DEBUG');
        } else {
            putenv('APP_DEBUG='.$previous);
        }
    }
}
