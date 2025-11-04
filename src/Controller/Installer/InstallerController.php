<?php

declare(strict_types=1);

namespace App\Controller\Installer;

use App\Doctrine\Health\SqliteHealthChecker;
use App\Form\Setup\AdminAccountType;
use App\Form\Setup\EnvironmentSettingsType;
use App\Form\Setup\StorageSettingsType;
use App\Installer\DefaultProjects;
use App\Installer\DefaultSystemSettings;
use App\Setup\SetupConfiguration;
use App\Setup\SetupAccessToken;
use App\Setup\SetupHelpLoader;
use App\Setup\SetupState;
use App\Bootstrap\RootEntryPoint;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
final class InstallerController extends AbstractController
{
    private const STEPS = ['diagnostics', 'environment', 'storage', 'admin', 'summary'];

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%app.user_database_path%')] private readonly string $userDatabasePath,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly SetupState $setupState,
        private readonly SetupAccessToken $setupAccessToken,
        private readonly SetupConfiguration $setupConfiguration,
        private readonly SetupHelpLoader $helpLoader,
    ) {
    }

    #[Route('/setup', name: 'app_installer', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        if ($this->setupState->isCompleted()) {
            return $this->redirect('/admin');
        }

        $availableSteps = $this->computeAvailableSteps();
        $requestedStep = (string) $request->query->get('step', self::STEPS[0]);
        $currentStep = \in_array($requestedStep, $availableSteps, true)
            ? $requestedStep
            : ($availableSteps[array_key_last($availableSteps)] ?? self::STEPS[0]);

        return $this->renderStep($request, $currentStep, [], $availableSteps);
    }

    #[Route('/setup/diagnostics', name: 'app_installer_diagnostics', methods: ['POST'])]
    public function diagnostics(): JsonResponse
    {
        if ($this->setupState->isCompleted()) {
            return $this->json(['error' => 'Setup already completed.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'diagnostics' => $this->gatherDiagnostics(),
        ]);
    }

    #[Route('/setup/environment', name: 'app_installer_environment_save', methods: ['POST'])]
    public function saveEnvironment(Request $request): Response
    {
        $form = $this->createEnvironmentForm();
        $this->processForm($form, $request);

        if (! $form->isSubmitted() || ! $form->isValid()) {
            if ($this->isJsonRequest($request)) {
                return $this->json(['errors' => $this->collectFormErrors($form)], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return $this->renderStep($request, 'environment', ['environment_form' => $form], $this->computeAvailableSteps());
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData() ?? [];

        $this->setupConfiguration->rememberSystemSettings($this->extractSystemSettings($data));
        $this->setupConfiguration->rememberEnvironmentOverrides($this->extractEnvironmentOverrides($data));

        if ($this->isJsonRequest($request)) {
            return $this->json([
                'success' => true,
                'environment_overrides' => $this->setupConfiguration->getEnvironmentOverrides(),
                'available_steps' => $this->computeAvailableSteps(),
            ]);
        }

        $this->addFlash('success', 'Environment settings saved.');

        return $this->redirectToRoute('app_installer', ['step' => 'environment']);
    }

    #[Route('/setup/storage', name: 'app_installer_storage_save', methods: ['POST'])]
    public function saveStorage(Request $request): Response
    {
        $form = $this->createStorageForm();
        $this->processForm($form, $request);

        if (! $form->isSubmitted() || ! $form->isValid()) {
            if ($this->isJsonRequest($request)) {
                return $this->json(['errors' => $this->collectFormErrors($form)], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return $this->renderStep($request, 'storage', ['storage_form' => $form], $this->computeAvailableSteps());
        }

        /** @var array{root:string} $data */
        $data = $form->getData() ?? ['root' => ''];
        $this->setupConfiguration->rememberStorageConfig($data);

        if ($this->isJsonRequest($request)) {
            return $this->json([
                'success' => true,
                'storage' => $this->setupConfiguration->getStorageConfig(),
                'available_steps' => $this->computeAvailableSteps(),
            ]);
        }

        $this->addFlash('success', 'Storage settings saved.');

        return $this->redirectToRoute('app_installer', ['step' => 'storage']);
    }

    #[Route('/setup/admin', name: 'app_installer_admin_save', methods: ['POST'])]
    public function saveAdmin(Request $request): Response
    {
        $helpTooltips = $this->mapHelpTooltips($this->helpLoader->load($request->getLocale()));
        $form = $this->createAdminForm($helpTooltips);
        $this->processForm($form, $request);

        if (! $form->isSubmitted() || ! $form->isValid()) {
            if ($this->isJsonRequest($request)) {
                return $this->json(['errors' => $this->collectFormErrors($form)], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return $this->renderStep($request, 'admin', ['admin_form' => $form], $this->computeAvailableSteps());
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData() ?? [];

        $this->setupConfiguration->rememberAdminAccount([
            'email' => (string) ($data['email'] ?? ''),
            'display_name' => (string) ($data['display_name'] ?? ''),
            'password' => (string) ($data['password'] ?? ''),
            'locale' => (string) ($data['locale'] ?? ''),
            'timezone' => (string) ($data['timezone'] ?? ''),
            'require_mfa' => (bool) ($data['require_mfa'] ?? false),
            'recovery_email' => (string) ($data['recovery_email'] ?? ''),
            'recovery_phone' => \is_string($data['recovery_phone'] ?? null) ? $data['recovery_phone'] : null,
        ]);

        if ($this->isJsonRequest($request)) {
            return $this->json([
                'success' => true,
                'admin' => $this->setupConfiguration->getAdminAccount(),
                'available_steps' => $this->computeAvailableSteps(),
            ]);
        }

        $this->addFlash('success', 'Administrator details saved.');

        return $this->redirectToRoute('app_installer', ['step' => 'admin']);
    }

    /**
     * @return array<string, mixed>
     */
    private function gatherDiagnostics(): array
    {
        $extensions = $this->gatherExtensionDiagnostics();

        $connectionParams = $this->connection->getParams();
        $primaryPath = (string) ($connectionParams['path'] ?? $connectionParams['dbname'] ?? '');
        if ($primaryPath === '' && isset($connectionParams['url'])) {
            $primaryPath = $this->extractPathFromUrl((string) $connectionParams['url']);
        }
        if ($primaryPath === '') {
            $primaryPath = $this->projectDir.'/var/system.brain';
        }

        $primaryExists = $primaryPath !== '' && is_file($primaryPath);
        $secondaryExists = $this->userDatabasePath !== '' && is_file($this->userDatabasePath);

        $sqliteReport = [
            'primary_path' => $primaryPath,
            'secondary_path' => $this->userDatabasePath,
            'primary_exists' => $primaryExists,
            'secondary_exists' => $secondaryExists,
            'secondary_attached' => false,
            'busy_timeout_ms' => 0,
        ];

        if ($primaryExists && $secondaryExists) {
            try {
                $sqliteReport = (new SqliteHealthChecker($this->connection, $this->userDatabasePath))->check()->toArray();
            } catch (\Throwable $exception) {
                $sqliteReport['error'] = $exception->getMessage();
            }
        }

        return [
            'php_version' => PHP_VERSION,
            'extensions' => $extensions,
            'sqlite' => $sqliteReport,
            'rewrite' => $this->gatherRewriteDiagnostics(),
            'filesystem' => $this->gatherFilesystemDiagnostics($this->projectDir),
        ];
    }

    private function renderStep(Request $request, string $currentStep, array $formOverrides = [], ?array $availableSteps = null): Response
    {
        $template = sprintf('pages/installer/%s.html.twig', $currentStep);
        $templatePath = $this->getParameter('kernel.project_dir').'/templates/'.$template;
        if (!is_file($templatePath)) {
            $template = 'pages/installer/diagnostics.html.twig';
        }

        $helpEntries = $this->helpLoader->load($request->getLocale());
        $helpTooltips = $this->mapHelpTooltips($helpEntries);
        $forms = $this->buildFormViews($formOverrides, $helpTooltips);
        $availableSteps ??= $this->computeAvailableSteps();

        return $this->render($template, array_merge([
            'steps' => self::STEPS,
            'current_step' => $currentStep,
            'diagnostics' => $this->gatherDiagnostics(),
            'default_settings' => DefaultSystemSettings::all(),
            'default_projects' => DefaultProjects::all(),
            'environment_settings' => $this->setupConfiguration->getSystemSettings(),
            'environment_overrides' => $this->setupConfiguration->getEnvironmentOverrides(),
            'storage_config' => $this->setupConfiguration->getStorageConfig(),
            'admin_account' => $this->setupConfiguration->getAdminAccount(),
            'help' => $helpEntries,
            'help_tooltips' => $helpTooltips,
            'locale_choices' => $this->collectLocaleChoices(),
            'timezone_choices' => $this->collectTimezoneChoices(),
            'available_steps' => $availableSteps,
            'setup' => [
                'databases_exist' => $this->setupState->databasesExist(),
                'action_steps' => $this->buildSetupActionSteps(),
                'action_token' => $this->setupAccessToken->issue(),
            ],
        ], $forms));
    }

    /**
     * @return array<string,string>
     */
    private function collectLocaleChoices(): array
    {
        $choices = [];

        $translationDir = $this->projectDir.'/translations';
        if (is_dir($translationDir)) {
            foreach (scandir($translationDir) ?: [] as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                if (!is_file($translationDir.'/'.$file)) {
                    continue;
                }

                $parts = explode('.', $file);
                if (count($parts) < 3) {
                    continue;
                }

                $locale = $parts[count($parts) - 2] ?? '';
                if (!is_string($locale) || $locale === '') {
                    continue;
                }

                $choices[$locale] = $locale;
            }
        }

        if ($choices === []) {
            $choices['en'] = 'en';
        }

        ksort($choices);

        return $choices;
    }

    /**
     * @return array<string,string>
     */
    private function collectTimezoneChoices(): array
    {
        $timezones = [];
        foreach (\DateTimeZone::listIdentifiers() as $identifier) {
            $timezones[$identifier] = $identifier;
        }

        return $timezones;
    }

    /**
     * @return list<string>
     */
    private function computeAvailableSteps(): array
    {
        $available = ['diagnostics', 'environment'];

        if ($this->setupConfiguration->hasEnvironmentOverrides()) {
            $available[] = 'storage';
        }

        if ($this->setupConfiguration->hasStorageConfig()) {
            $available[] = 'admin';
        }

        if ($this->setupConfiguration->hasAdminAccount()) {
            $available[] = 'summary';
        }

        return $available;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $groupedHelp
     *
     * @return array<string, array<string, mixed>>
     */
    private function mapHelpTooltips(array $groupedHelp): array
    {
        $indexed = [];

        foreach ($groupedHelp as $entries) {
            if (!\is_iterable($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!\is_array($entry)) {
                    continue;
                }

                if (($entry['type'] ?? null) !== 'tooltip') {
                    continue;
                }

                $target = $entry['target'] ?? null;
                if (!\is_string($target) || $target === '') {
                    continue;
                }

                $indexed[$target] = $entry;
            }
        }

        return $indexed;
    }

    private function extractPathFromUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $components = parse_url($url);
        if (!\is_array($components)) {
            return '';
        }

        if (isset($components['path']) && $components['path'] !== '') {
            return $components['path'];
        }

        return '';
    }

    /**
     * @return array<string, bool|string>
     */
    private function gatherRewriteDiagnostics(): array
    {
        $rootEntryActive = $this->readFlag(RootEntryPoint::FLAG_ROOT_ENTRY);
        $forced = $this->readFlag(RootEntryPoint::FLAG_FORCED);

        $mode = match (true) {
            $forced => 'forced',
            $rootEntryActive => 'compatibility',
            default => 'rewrite',
        };

        $route = $this->readStringFlag(RootEntryPoint::FLAG_ROUTE, '/');
        $originalUri = $this->readStringFlag(RootEntryPoint::FLAG_ORIGINAL_URI, $_SERVER['REQUEST_URI'] ?? '/');
        $requestUri = $this->readStringFlag(RootEntryPoint::FLAG_REQUEST_URI, $_SERVER['REQUEST_URI'] ?? '/');

        return [
            'enabled' => !$rootEntryActive,
            'mode' => $mode,
            'forced' => $forced,
            'route' => $route,
            'original_uri' => $originalUri,
            'request_uri' => $requestUri,
        ];
    }

    private function readFlag(string $key): bool
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return $value === 1;
        }

        if (\is_string($value)) {
            $value = strtolower(trim($value));

            return \in_array($value, ['1', 'true', 'on', 'yes'], true);
        }

        return false;
    }

    private function readStringFlag(string $key, string $fallback): string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        return \is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * @return list<array{code:string,label:string,available:bool,required:bool,hint:string}>
     */
    private function gatherExtensionDiagnostics(): array
    {
        $requirements = [
            'ext-intl' => [
                'label' => 'intl',
                'hint' => 'Install/enable the Intl extension (`php-intl`) to provide locale, date, and number formatting.',
                'required' => true,
            ],
            'ext-sqlite3' => [
                'label' => 'sqlite3',
                'hint' => 'Enable the SQLite3 extension to power the system and user data stores.',
                'required' => true,
            ],
            'ext-fileinfo' => [
                'label' => 'fileinfo',
                'hint' => 'Enable the Fileinfo extension for media uploads and MIME detection.',
                'required' => true,
            ],
            'ext-json' => [
                'label' => 'json',
                'hint' => 'Enable the JSON extension. Most PHP distributions ship it by default.',
                'required' => true,
            ],
            'ext-mbstring' => [
                'label' => 'mbstring',
                'hint' => 'Enable mbstring for multibyte string handling (content editing & localisation).',
                'required' => true,
            ],
            'ext-ctype' => [
                'label' => 'ctype',
                'hint' => 'Enable the ctype extension for validation helpers.',
                'required' => true,
            ],
        ];

        $extensions = [];

        foreach ($requirements as $code => $meta) {
            $extensionName = str_starts_with($code, 'ext-') ? substr($code, 4) : $code;
            $available = \extension_loaded($extensionName);

            $extensions[] = [
                'code' => $code,
                'label' => $meta['label'],
                'available' => $available,
                'required' => (bool) $meta['required'],
                'hint' => $meta['hint'],
            ];
        }

        return $extensions;
    }

    /**
     * @return list<array{code:string,label:string,path:string,exists:bool,writable:bool,parent_writable:bool,hint:string}>
     */
    private function gatherFilesystemDiagnostics(string $projectDir): array
    {
        $paths = [
            'var_root' => [
                'label' => 'var/ directory',
                'path' => $projectDir.'/var',
                'hint' => 'Ensure the web user can write to var/ for cache, logs, and databases.',
            ],
            'var_cache' => [
                'label' => 'var/cache',
                'path' => $projectDir.'/var/cache',
                'hint' => 'Symfony stores runtime cache here; make sure it exists and is writable.',
            ],
            'var_log' => [
                'label' => 'var/log',
                'path' => $projectDir.'/var/log',
                'hint' => 'Application logs are written here; grant write access.',
            ],
            'var_snapshots' => [
                'label' => 'var/snapshots',
                'path' => $projectDir.'/var/snapshots',
                'hint' => 'Published site snapshots live here; create the directory if missing.',
            ],
            'var_uploads' => [
                'label' => 'var/uploads',
                'path' => $projectDir.'/var/uploads',
                'hint' => 'Uploads and temporary media storage; ensure the directory exists and is writable.',
            ],
            'var_themes' => [
                'label' => 'var/themes',
                'path' => $projectDir.'/var/themes',
                'hint' => 'Theme bundles unpack here; create and grant write permissions.',
            ],
            'public_assets' => [
                'label' => 'public/assets',
                'path' => $projectDir.'/public/assets',
                'hint' => 'AssetMapper builds JS/CSS here; make sure deploy user can write.',
            ],
        ];

        $reports = [];

        foreach ($paths as $code => $details) {
            $path = $details['path'];
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $parentWritable = is_writable(\dirname($path));

            $reports[] = [
                'code' => $code,
                'label' => $details['label'],
                'path' => $path,
                'exists' => $exists,
                'writable' => $writable,
                'parent_writable' => $parentWritable,
                'hint' => $details['hint'],
            ];
        }

        return $reports;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildSetupActionSteps(): array
    {
        $environment = $this->resolveTargetEnvironment();

        return [
            [
                'type' => 'log',
                'message' => 'Start setup routine...',
            ],
            [
                'type' => 'write_env',
            ],
            [
                'type' => 'configure',
            ],
            [
                'type' => 'prepare_payload',
            ],
            [
                'type' => 'init',
                'environment' => $environment,
            ],
            [
                'type' => 'lock',
            ],
            [
                'type' => 'log',
                'message' => 'Setup completed successfully.',
            ],
        ];
    }

    private function resolveTargetEnvironment(): string
    {
        $manifest = $this->projectDir.'/manifest.json';

        if (is_file($manifest)) {
            $contents = file_get_contents($manifest);
            if (\is_string($contents)) {
                $decoded = json_decode($contents, true);
                if (\is_array($decoded) && isset($decoded['environment']) && \is_string($decoded['environment']) && $decoded['environment'] !== '') {
                    return $decoded['environment'];
                }
            }
        }

        return $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev';
    }

    /**
     * @return array<string, mixed>
     */
    private function extractSystemSettings(array $data): array
    {
        return [
            'core.instance_name' => (string) ($data['instance_name'] ?? ''),
            'core.tagline' => (string) ($data['tagline'] ?? ''),
            'core.support_email' => (string) ($data['support_email'] ?? ''),
            'core.url' => (string) ($data['base_url'] ?? ''),
            'core.locale' => (string) ($data['locale'] ?? 'en'),
            'core.timezone' => (string) ($data['timezone'] ?? 'UTC'),
            'core.user_registration' => (bool) ($data['user_registration'] ?? false),
            'core.maintenance_mode' => (bool) ($data['maintenance_mode'] ?? false),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractEnvironmentOverrides(array $data): array
    {
        $overrides = [
            'APP_ENV' => (string) ($data['environment'] ?? 'dev'),
            'APP_DEBUG' => ((bool) ($data['debug'] ?? false)) ? '1' : '0',
        ];

        $secret = $data['secret'] ?? null;
        if (\is_string($secret) && $secret !== '') {
            $overrides['APP_SECRET'] = $secret;
        }

        return $overrides;
    }

    private function isJsonRequest(Request $request): bool
    {
        return $request->getContentTypeFormat() === 'json' || str_contains((string) $request->headers->get('Content-Type'), 'application/json');
    }

    private function processForm(FormInterface $form, Request $request): void
    {
        if ($this->isJsonRequest($request)) {
            $data = json_decode((string) $request->getContent(), true);
            if (!\is_array($data)) {
                $data = [];
            }

            $form->submit($data);

            return;
        }

        $form->handleRequest($request);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function collectFormErrors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $name = $origin instanceof FormInterface ? $origin->getName() : $form->getName();
            $errors[$name][] = $error->getMessage();
        }

        return $errors;
    }

    /**
     * @param array<string, FormInterface> $overrides
     * @param array<string, array<string, mixed>> $tooltips
     *
     * @return array<string, mixed>
     */
    private function buildFormViews(array $overrides = [], array $tooltips = []): array
    {
        $environmentForm = $overrides['environment_form'] ?? $this->createEnvironmentForm();
        $storageForm = $overrides['storage_form'] ?? $this->createStorageForm();
        $adminForm = $overrides['admin_form'] ?? $this->createAdminForm($tooltips);

        return [
            'environment_form' => $environmentForm->createView(),
            'storage_form' => $storageForm->createView(),
            'admin_form' => $adminForm->createView(),
        ];
    }

    private function createEnvironmentForm(): FormInterface
    {
        return $this->createForm(EnvironmentSettingsType::class, $this->prepareEnvironmentFormData(), [
            'action' => $this->generateUrl('app_installer_environment_save'),
            'method' => 'POST',
            'locale_choices' => $this->collectLocaleChoices(),
            'timezone_choices' => $this->collectTimezoneChoices(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareEnvironmentFormData(): array
    {
        $settings = $this->setupConfiguration->getSystemSettings();
        $env = $this->setupConfiguration->getEnvironmentOverrides();

        return [
            'environment' => $env['APP_ENV'] ?? 'dev',
            'debug' => ($env['APP_DEBUG'] ?? '1') !== '0',
            'secret' => $env['APP_SECRET'] ?? '',
            'instance_name' => (string) ($settings['core.instance_name'] ?? ''),
            'tagline' => (string) ($settings['core.tagline'] ?? ''),
            'support_email' => (string) ($settings['core.support_email'] ?? ''),
            'base_url' => (string) ($settings['core.url'] ?? ''),
            'locale' => (string) ($settings['core.locale'] ?? 'en'),
            'timezone' => (string) ($settings['core.timezone'] ?? 'UTC'),
            'user_registration' => (bool) ($settings['core.user_registration'] ?? false),
            'maintenance_mode' => (bool) ($settings['core.maintenance_mode'] ?? false),
        ];
    }

    private function createStorageForm(): FormInterface
    {
        return $this->createForm(StorageSettingsType::class, $this->setupConfiguration->getStorageConfig(), [
            'action' => $this->generateUrl('app_installer_storage_save'),
            'method' => 'POST',
        ]);
    }

    private function createAdminForm(array $tooltips = []): FormInterface
    {
        $settings = $this->setupConfiguration->getSystemSettings();
        $policy = [];
        if (isset($settings['core.users.password_policy']) && \is_array($settings['core.users.password_policy'])) {
            $policy = $settings['core.users.password_policy'];
        }

        return $this->createForm(AdminAccountType::class, $this->prepareAdminFormData(), [
            'action' => $this->generateUrl('app_installer_admin_save'),
            'method' => 'POST',
            'password_policy' => $policy,
            'locale_choices' => $this->collectLocaleChoices(),
            'timezone_choices' => $this->collectTimezoneChoices(),
            'tooltips' => $tooltips,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareAdminFormData(): array
    {
        $admin = $this->setupConfiguration->getAdminAccount();

        return [
            'email' => $admin['email'],
            'display_name' => $admin['display_name'],
            'password' => '',
            'locale' => $admin['locale'],
            'timezone' => $admin['timezone'],
            'require_mfa' => $admin['require_mfa'],
            'recovery_email' => $admin['recovery_email'],
            'recovery_phone' => $admin['recovery_phone'],
        ];
    }
}
