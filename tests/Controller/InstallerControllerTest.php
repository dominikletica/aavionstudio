<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Bootstrap\RootEntryPoint;
use App\Setup\SetupAccessToken;
use App\Setup\SetupState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class InstallerControllerTest extends WebTestCase
{
    private string $projectDir;
    private string $systemDatabasePath;
    private string $userDatabasePath;
    private string $setupLockPath;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = \dirname(__DIR__, 2);
        $varDir = $this->projectDir.'/var/test';

        $this->systemDatabasePath = $varDir.'/system.brain';
        $this->userDatabasePath = $varDir.'/user.brain';
        $this->setupLockPath = $varDir.'/.setup.lock';

        $filesystem = new Filesystem();
        $filesystem->remove([$this->systemDatabasePath, $this->userDatabasePath, $this->setupLockPath]);
        $filesystem->mkdir($varDir);
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
        $this->assertSelectorExists('table[data-testid="diagnostics-extensions"]');
        $this->assertSelectorExists('table[data-testid="diagnostics-filesystem"]');
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

    public function testSetupActionRejectsRequestsWithoutToken(): void
    {
        $client = static::createClient();

        $client->request('POST', '/setup/action', [
            'context' => 'setup',
            'steps' => json_encode([
                ['type' => 'log', 'message' => 'noop'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testSetupActionAcceptsRequestsWithSessionToken(): void
    {
        $client = static::createClient();
        $token = $this->issueSetupToken($client);

        $client->request('POST', '/setup/action', [
            'context' => 'setup',
            'token' => $token,
            'steps' => json_encode([
                ['type' => 'log', 'message' => 'noop'],
                ['type' => 'configure'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertResponseIsSuccessful();
        (string) $client->getResponse()->getContent();
    }

    public function testNonSetupRoutesRedirectToSetupUntilProvisioned(): void
    {
        $client = static::createClient();
        $setupState = self::getContainer()->get(SetupState::class);

        self::assertTrue($setupState->missingDatabases(), 'Expected databases to be absent before guarding non-setup routes.');

        $client->request('GET', '/login');

        $this->assertResponseRedirects('/setup');
    }

    public function testSetupCompletionCreatesDatabasesAndLocksWizard(): void
    {
        $client = static::createClient();

        $token = $this->issueSetupToken($client);

        $filesystem = new Filesystem();
        $filesystem->touch($this->systemDatabasePath);
        $filesystem->touch($this->userDatabasePath);

        $client->request('POST', '/setup/action', [
            'context' => 'setup',
            'token' => $token,
            'steps' => json_encode([
                ['type' => 'log', 'message' => 'Testrun'],
                ['type' => 'configure'],
                ['type' => 'lock'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertResponseIsSuccessful();
        // Consume the streamed body to avoid PHPUnit reporting open buffers.
        (string) $client->getResponse()->getContent();

        self::assertFileExists($this->systemDatabasePath, 'Primary database should be created during setup completion.');
        self::assertFileExists($this->userDatabasePath, 'User database should be created during setup completion.');
        self::assertFileExists($this->setupLockPath, 'Setup completion should lock the wizard.');

        $client->request('GET', '/setup');
        $this->assertResponseRedirects('/admin');
    }

    public function testSummaryShowsFinalizeFormEvenIfDatabasesExist(): void
    {
        $filesystem = new Filesystem();
        $filesystem->touch($this->systemDatabasePath);
        $filesystem->touch($this->userDatabasePath);

        $client = static::createClient();
        $crawler = $client->request('GET', '/setup?step=summary');

        $this->assertResponseIsSuccessful();
        $trigger = $crawler->filter('[data-action-trigger]');
        $this->assertSame(1, $trigger->count());
        $this->assertSame('setup', (string) $trigger->attr('data-action-context'));
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();
        $filesystem->touch($this->setupLockPath);

        parent::tearDown();
    }

    private function issueSetupToken(KernelBrowser $client): string
    {
        $client->request('GET', '/setup');
        $request = $client->getRequest();
        self::assertNotNull($request);

        $session = $request->getSession();
        self::assertNotNull($session);

        $token = $session->get(SetupAccessToken::SESSION_KEY);
        self::assertIsString($token);

        return $token;
    }
}
