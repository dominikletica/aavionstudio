<?php

declare(strict_types=1);

namespace App\Tests\Controller\Error;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ErrorControllerTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testNotFoundRendersCustomTemplate(): void
    {
        $client = static::createClient();
        $client->request('GET', '/this-path-should-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Page not found');
        self::assertSelectorTextContains('section h2', 'Debug details');
    }

    public function testProductionHidesDebugDetails(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->request('GET', '/another-missing-path');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Page not found');
        self::assertSelectorNotExists('section h2');
    }
}
