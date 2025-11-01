<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DemoControllerTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testThemeDemoPageRendersComponentShowcase(): void
    {
        $client = static::createClient();
        $client->request('GET', '/_themedemo');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main h2', 'UI Component Showcase');
        $this->assertSelectorTextContains('main', 'Button variants');
        $this->assertSelectorExists('main table');
    }
}
