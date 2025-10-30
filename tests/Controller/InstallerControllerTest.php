<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Bootstrap\RootEntryPoint;
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
        $this->assertSelectorTextContains('main', 'Docroot & rewrite status');
    }

    public function testSetupWizardShowsCompatibilityWarningWhenRootEntryActive(): void
    {
        $client = static::createClient();

        $_SERVER[RootEntryPoint::FLAG_ROOT_ENTRY] = '1';
        $_SERVER[RootEntryPoint::FLAG_FORCED] = '0';
        $_SERVER[RootEntryPoint::FLAG_ROUTE] = '/';
        $_SERVER[RootEntryPoint::FLAG_ORIGINAL_URI] = '/index.php';
        $_SERVER[RootEntryPoint::FLAG_REQUEST_URI] = '/';

        try {
            $client->request('GET', '/setup');

            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('main p[role="alert"]', 'Configure the web server');
        } finally {
            unset(
                $_SERVER[RootEntryPoint::FLAG_ROOT_ENTRY],
                $_SERVER[RootEntryPoint::FLAG_FORCED],
                $_SERVER[RootEntryPoint::FLAG_ROUTE],
                $_SERVER[RootEntryPoint::FLAG_ORIGINAL_URI],
                $_SERVER[RootEntryPoint::FLAG_REQUEST_URI],
            );
        }
    }

    public function testSetupWizardNotesForcedCompatibilityMode(): void
    {
        $client = static::createClient();

        $_SERVER[RootEntryPoint::FLAG_ROOT_ENTRY] = '1';
        $_SERVER[RootEntryPoint::FLAG_FORCED] = '1';
        $_SERVER[RootEntryPoint::FLAG_ROUTE] = '/';
        $_SERVER[RootEntryPoint::FLAG_ORIGINAL_URI] = '/index.php';
        $_SERVER[RootEntryPoint::FLAG_REQUEST_URI] = '/';

        try {
            $client->request('GET', '/setup');

            $this->assertResponseIsSuccessful();
            $this->assertSelectorNotExists('main p[role="alert"]');
            $this->assertSelectorTextContains('main', 'Compatibility mode is intentionally forced');
        } finally {
            unset(
                $_SERVER[RootEntryPoint::FLAG_ROOT_ENTRY],
                $_SERVER[RootEntryPoint::FLAG_FORCED],
                $_SERVER[RootEntryPoint::FLAG_ROUTE],
                $_SERVER[RootEntryPoint::FLAG_ORIGINAL_URI],
                $_SERVER[RootEntryPoint::FLAG_REQUEST_URI],
            );
        }
    }
}
