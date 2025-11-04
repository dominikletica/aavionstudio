<?php

declare(strict_types=1);

namespace App\Controller\Installer;

use App\Doctrine\Health\SqliteHealthChecker;
use App\Form\Setup\AdminAccountType;
use App\Form\Setup\EnvironmentSettingsType;
use App\Form\Setup\StorageSettingsType;
use App\Installer\DefaultProjects;
use App\Installer\DefaultSystemSettings;
use App\Internationalization\LocaleProvider;
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
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly LocaleProvider $localeProvider,
        private readonly TranslatorInterface $translator,
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
            return $this->json(['error' => $this->translator->trans('installer.errors.already_completed')], Response::HTTP_BAD_REQUEST);
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

        $this->addFlash('success', $this->translator->trans('flash.installer.environment_saved'));

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

        $this->addFlash('success', $this->translator->trans('flash.installer.storage_saved'));

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

        $this->addFlash('success', $this->translator->trans('flash.installer.admin_saved'));

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

        foreach ($this->localeProvider->available() as $locale) {
            $choices[$locale] = $locale;
        }

        if ($choices === []) {
            $fallback = $this->localeProvider->fallback();
            $choices[$fallback] = $fallback;
        }

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
                'hint_key' => 'installer.diagnostics.extensions.hints.intl',
                'required' => true,
            ],
            'ext-sqlite3' => [
                'label' => 'sqlite3',
                'hint_key' => 'installer.diagnostics.extensions.hints.sqlite',
                'required' => true,
            ],
            'ext-fileinfo' => [
                'label' => 'fileinfo',
                'hint_key' => 'installer.diagnostics.extensions.hints.fileinfo',
                'required' => true,
            ],
            'ext-json' => [
                'label' => 'json',
                'hint_key' => 'installer.diagnostics.extensions.hints.json',
                'required' => true,
            ],
            'ext-mbstring' => [
                'label' => 'mbstring',
                'hint_key' => 'installer.diagnostics.extensions.hints.mbstring',
                'required' => true,
            ],
            'ext-ctype' => [
                'label' => 'ctype',
                'hint_key' => 'installer.diagnostics.extensions.hints.ctype',
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
                'hint' => $this->translator->trans($meta['hint_key']),
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
                'label_key' => 'installer.diagnostics.filesystem.labels.var_root',
                'path' => $projectDir.'/var',
                'hint_key' => 'installer.diagnostics.filesystem.hints.var_root',
            ],
            'var_cache' => [
                'label_key' => 'installer.diagnostics.filesystem.labels.var_cache',
                'path' => $projectDir.'/var/cache',
                'hint_key' => 'installer.diagnostics.filesystem.hints.var_cache',
            ],
            'var_log' => [
                'label_key' => 'installer.diagnostics.filesystem.labels.var_log',
                'path' => $projectDir.'/var/log',
                'hint_key' => 'installer.diagnostics.filesystem.hints.var_log',
            ],
            'var_snapshots' => [
                'label_key' => 'installer.diagnostics.filesystem.labels.var_snapshots',
                'path' => $projectDir.'/var/snapshots',
                'hint_key' => 'installer.diagnostics.filesystem.hints.var_snapshots',
            ],
            'var_uploads' => [
                'label_key' => 'installer.diagnostics.filesystem.labels.var_uploads',
                'path' => $projectDir.'/var/uploads',
                'hint_key' => 'installer.diagnostics.filesystem.hints.var_uploads',
            ],
            'var_themes' => [
                'label_key' => 'installer.diagnostics.filesystem.labels.var_themes',
                'path' => $projectDir.'/var/themes',
                'hint_key' => 'installer.diagnostics.filesystem.hints.var_themes',
            ],
            'public_assets' => [
                'label_key' => 'installer.diagnostics.filesystem.labels.public_assets',
                'path' => $projectDir.'/public/assets',
                'hint_key' => 'installer.diagnostics.filesystem.hints.public_assets',
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
                'label' => $this->translator->trans($details['label_key']),
                'path' => $path,
                'exists' => $exists,
                'writable' => $writable,
                'parent_writable' => $parentWritable,
                'hint' => $this->translator->trans($details['hint_key']),
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
                'message' => $this->translator->trans('installer.action.log.start'),
            ],
            [
                'type' => 'write_env',
            ],
            [
                'type' => 'prepare_payload',
            ],
            [
                'type' => 'init',
                'environment' => $environment,
            ],
            [
                'type' => 'configure',
            ],
            [
                'type' => 'lock',
            ],
            [
                'type' => 'log',
                'message' => $this->translator->trans('installer.action.log.completed'),
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
