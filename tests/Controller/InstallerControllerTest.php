<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Bootstrap\RootEntryPoint;
use App\Setup\SetupAccessToken;
use App\Setup\SetupState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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

        $logFiles = glob($this->projectDir.'/var/log/setup/*.ndjson');
        self::assertIsArray($logFiles);
        self::assertNotEmpty($logFiles, 'Setup should write a log file to var/log/setup/.');

        $client->request('GET', '/setup');
        $this->assertResponseRedirects('/admin');
    }

    public function testSummaryShowsFinalizeFormEvenIfDatabasesExist(): void
    {
        $client = static::createClient();

        $filesystem = new Filesystem();
        $filesystem->touch($this->systemDatabasePath);
        $filesystem->touch($this->userDatabasePath);

        $this->completeEnvironmentStep($client);
        $this->completeStorageStep($client);
        $this->completeAdminStep($client);

        $crawler = $client->request('GET', '/setup?step=summary');

        $this->assertResponseIsSuccessful();
        $trigger = $crawler->filter('[data-action-trigger]');
        $this->assertSame(1, $trigger->count());
        $this->assertSame('setup', (string) $trigger->attr('data-action-context'));
    }

    public function testWizardRestrictsDirectNavigationToFutureSteps(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/setup?step=summary');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main h2', 'Environment defaults');
        $this->assertGreaterThan(
            0,
            $crawler->filter('nav[aria-label="Wizard Steps"] span[aria-disabled="true"]')->count()
        );
    }

    public function testInstallerFormsExposeContextualTooltips(): void
    {
        $client = static::createClient();
        $client->request('GET', '/setup?step=environment');

        $this->assertSelectorExists('label.tooltip[for="environment_settings_environment"][data-tooltip]');
        $this->assertSelectorExists('label.tooltip[for="environment_settings_debug"][data-tooltip]');
        $this->assertSelectorExists('label.tooltip[for="environment_settings_secret"][data-tooltip]');
        $this->assertSelectorExists('label.tooltip[for="environment_settings_timezone"][data-tooltip]');
        $this->assertSelectorExists('label.tooltip[for="environment_settings_user_registration"][data-tooltip]');
        $this->assertSelectorExists('[data-controller="setup-secret"] button[data-action="setup-secret#generate"]');

        $this->completeEnvironmentStep($client);
        $this->completeStorageStep($client);

        $client->request('GET', '/setup?step=admin');
        $this->assertSelectorExists('label.tooltip[for="admin_account_email"][data-tooltip]');
        $this->assertSelectorExists('label.tooltip[for="admin_account_password_first"][data-tooltip]');
        $this->assertSelectorExists('label.tooltip[for="admin_account_require_mfa"][data-tooltip]');
    }

    public function testSummaryFinalizeButtonHasTooltip(): void
    {
        $client = static::createClient();
        $this->completeEnvironmentStep($client);
        $this->completeStorageStep($client);
        $this->completeAdminStep($client);

        $crawler = $client->request('GET', '/setup?step=summary');

        $button = $crawler->filter('button[data-action-trigger]');
        $this->assertSame(1, $button->count());
        $classAttr = (string) $button->attr('class');
        $this->assertStringContainsString('btn', $classAttr);
        $this->assertStringContainsString('tooltip', $classAttr);
        $this->assertNotSame('', (string) $button->attr('data-tooltip'));
    }

    public function testEnvironmentFormSubmissionPersistsConfiguration(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/setup?step=environment');

        $formButton = $crawler->selectButton('Save environment settings');
        $this->assertSame(1, $formButton->count(), 'Environment step should be reachable.');
        $form = $formButton->form();
        $form['environment_settings[environment]'] = 'prod';
        $form['environment_settings[debug]']->tick();
        $form['environment_settings[secret]'] = 'test-secret';
        $form['environment_settings[base_url]'] = 'https://example.com';
        $form['environment_settings[instance_name]'] = 'Demo Studio';
        $form['environment_settings[tagline]'] = 'Create boldly';
        $form['environment_settings[support_email]'] = 'support@example.com';
        $form['environment_settings[locale]'] = 'en';
        $form['environment_settings[timezone]'] = 'UTC';
        $form['environment_settings[user_registration]']->tick();
        $form['environment_settings[maintenance_mode]']->untick();

        $client->submit($form);
        $this->assertResponseRedirects('/setup?step=environment');
        $client->followRedirect();

        $data = $this->getInstallerSession($client)->get('_app.setup.configuration');
        self::assertIsArray($data);

        $overrides = $data['environment_overrides'] ?? [];

        self::assertSame('prod', $overrides['APP_ENV']);
        self::assertSame('1', $overrides['APP_DEBUG']);
        self::assertSame('test-secret', $overrides['APP_SECRET']);

        $settings = $data['system_settings'] ?? [];
        self::assertSame('Demo Studio', $settings['core.instance_name']);
        self::assertTrue($settings['core.user_registration']);
    }

    public function testStorageFormSubmissionPersistsRoot(): void
    {
        $client = static::createClient();
        $this->completeEnvironmentStep($client);
        $crawler = $client->request('GET', '/setup?step=storage');

        $formButton = $crawler->selectButton('Save storage settings');
        $this->assertSame(1, $formButton->count(), 'Storage step should be reachable.');
        $form = $formButton->form();
        $form['storage_settings[root]'] = '/mnt/data';

        $client->submit($form);
        $this->assertResponseRedirects('/setup?step=storage');
        $client->followRedirect();

        $data = $this->getInstallerSession($client)->get('_app.setup.configuration');
        self::assertIsArray($data);

        self::assertSame('/mnt/data', $data['storage']['root'] ?? null);
    }

    public function testAdminFormSubmissionPersistsAccount(): void
    {
        $client = static::createClient();
        $this->completeEnvironmentStep($client);
        $this->completeStorageStep($client);
        $crawler = $client->request('GET', '/setup?step=admin');

        $response = $client->getResponse();
        if ($response->isRedirection()) {
            $this->fail(sprintf('Admin step redirected to %s', $response->headers->get('Location')));
        }

        $formButton = $crawler->selectButton('Save administrator');
        $this->assertSame(1, $formButton->count(), 'Admin step should be reachable.');
        $form = $formButton->form();
        $form['admin_account[email]'] = 'admin@example.com';
        $form['admin_account[display_name]'] = 'Admin';
        $form['admin_account[password][first]'] = 'SecurePassword123!';
        $form['admin_account[password][second]'] = 'SecurePassword123!';
        $form['admin_account[locale]'] = 'en';
        $form['admin_account[timezone]'] = 'America/New_York';
        $form['admin_account[require_mfa]']->tick();
        $form['admin_account[recovery_email]'] = 'security@example.com';
        $form['admin_account[recovery_phone]'] = '+1555123456';

        $client->submit($form);
        $this->assertResponseRedirects('/setup?step=admin');
        $client->followRedirect();

        $data = $this->getInstallerSession($client)->get('_app.setup.configuration');
        self::assertIsArray($data);
        $admin = $data['admin'] ?? [];

        self::assertSame('admin@example.com', $admin['email']);
        self::assertSame('Admin', $admin['display_name']);
        self::assertSame('SecurePassword123!', $admin['password']);
        self::assertTrue($admin['require_mfa']);
    }

    public function testDiagnosticsEndpointReturnsJsonPayload(): void
    {
        $client = static::createClient();
        $client->request('POST', '/setup/diagnostics', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('diagnostics', $data);
        self::assertArrayHasKey('extensions', $data['diagnostics']);
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();
        $filesystem->touch($this->setupLockPath);
        $filesystem->remove(glob($this->projectDir.'/var/log/setup/*.ndjson') ?: []);

        parent::tearDown();
    }

    private function issueSetupToken(KernelBrowser $client): string
    {
        $client->request('GET', '/setup');
        $session = $this->getInstallerSession($client);

        $token = $session->get(SetupAccessToken::SESSION_KEY);
        self::assertIsString($token);

        return $token;
    }

    private function completeEnvironmentStep(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/setup?step=environment');

        $form = $crawler->selectButton('Save environment settings')->form();
        $form['environment_settings[environment]'] = 'prod';
        $form['environment_settings[debug]']->tick();
        $form['environment_settings[secret]'] = 'secret-value';
        $form['environment_settings[base_url]'] = 'https://example.com';
        $form['environment_settings[instance_name]'] = 'Demo';
        $form['environment_settings[tagline]'] = 'Tagline';
        $form['environment_settings[support_email]'] = 'support@example.com';
        $form['environment_settings[locale]'] = 'en';
        $form['environment_settings[timezone]'] = 'UTC';

        $client->submit($form);
        $this->assertResponseRedirects('/setup?step=environment');
        $client->followRedirect();
    }

    private function completeStorageStep(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/setup?step=storage');

        $form = $crawler->selectButton('Save storage settings')->form();
        $form['storage_settings[root]'] = '/var/storage/app';

        $client->submit($form);
        $this->assertResponseRedirects('/setup?step=storage');
        $client->followRedirect();
    }

    private function completeAdminStep(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/setup?step=admin');

        $form = $crawler->selectButton('Save administrator')->form();
        $form['admin_account[email]'] = 'admin@example.com';
        $form['admin_account[display_name]'] = 'Admin';
        $form['admin_account[password][first]'] = 'StrongPassword123!';
        $form['admin_account[password][second]'] = 'StrongPassword123!';
        $form['admin_account[locale]'] = 'en';
        $form['admin_account[timezone]'] = 'UTC';

        $client->submit($form);
        $this->assertResponseRedirects('/setup?step=admin');
        $client->followRedirect();
    }

    private function getInstallerSession(KernelBrowser $client): SessionInterface
    {
        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getContainer()->get('session.factory');
        $session = $sessionFactory->createSession();

        $cookie = $client->getCookieJar()->get($session->getName());
        self::assertNotNull($cookie, 'Expected installer session cookie to be set.');

        $session->setId($cookie->getValue());
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session;
    }
}
