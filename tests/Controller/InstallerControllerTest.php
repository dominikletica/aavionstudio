<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InstallerControllerTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testSetupWizardRendersSteps(): void
    {
        $client = static::createClient();
        $client->request('GET', '/setup');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('nav[aria-label="Wizard Steps"]');
        $this->assertSelectorTextContains('nav[aria-label="Wizard Steps"] ol li:first-child', 'Diagnostics');
        $this->assertSelectorTextContains('main h2', 'Environment diagnostics');
    }
}
