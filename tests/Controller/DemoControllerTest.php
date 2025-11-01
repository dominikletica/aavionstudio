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
        $this->assertSelectorExists('[data-controller="themedemo"]');
        $this->assertSelectorExists('turbo-frame#themedemo_tip');
        $this->assertSelectorExists('textarea[data-controller="codemirror"]');
    }

    public function testThemeDemoTipRouteReturnsHtmlFragment(): void
    {
        $client = static::createClient();
        $client->request('GET', '/_themedemo/tip?i=1');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'text/html; charset=UTF-8');
        $this->assertSelectorTextContains('article h3', 'Prefer partial components');
    }
}
