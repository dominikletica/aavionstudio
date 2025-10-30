<?php

declare(strict_types=1);

namespace App\Controller\Installer;

use App\Doctrine\Health\SqliteHealthChecker;
use App\Installer\DefaultProjects;
use App\Installer\DefaultSystemSettings;
use App\Bootstrap\RootEntryPoint;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
final class InstallerController extends AbstractController
{
    private const STEPS = ['diagnostics', 'environment', 'storage', 'admin', 'summary'];

    #[Route('/setup', name: 'app_installer')]
    public function __invoke(Request $request): Response
    {
        $requestedStep = (string) $request->query->get('step', self::STEPS[0]);
        $currentStep = \in_array($requestedStep, self::STEPS, true) ? $requestedStep : self::STEPS[0];

        return $this->render('installer/wizard.html.twig', [
            'steps' => self::STEPS,
            'current_step' => $currentStep,
            'diagnostics' => $this->gatherDiagnostics(),
            'default_settings' => DefaultSystemSettings::all(),
            'default_projects' => DefaultProjects::all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function gatherDiagnostics(): array
    {
        $extensions = $this->gatherExtensionDiagnostics();

        $projectDir = \dirname(__DIR__, 3);
        $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? sprintf('sqlite:///%s/var/system.brain', $projectDir);
        $userDatabasePath = $_ENV['APP_USER_DATABASE_PATH'] ?? $projectDir.'/var/user.brain';

        $defaultPrimaryPath = $projectDir.'/var/system.brain';

        try {
            $connection = DriverManager::getConnection(['url' => $databaseUrl]);
            $sqliteReport = (new SqliteHealthChecker($connection, $userDatabasePath))->check()->toArray();
            $connection->close();
        } catch (\Throwable $exception) {
            $sqliteReport = [
                'primary_path' => $defaultPrimaryPath,
                'secondary_path' => $userDatabasePath,
                'primary_exists' => false,
                'secondary_exists' => false,
                'secondary_attached' => false,
                'busy_timeout_ms' => 0,
                'error' => $exception->getMessage(),
            ];
        }

        return [
            'php_version' => PHP_VERSION,
            'extensions' => $extensions,
            'sqlite' => $sqliteReport,
            'rewrite' => $this->gatherRewriteDiagnostics(),
            'filesystem' => $this->gatherFilesystemDiagnostics($projectDir),
        ];
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
}
