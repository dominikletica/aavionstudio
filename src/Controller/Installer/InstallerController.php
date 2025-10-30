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
        $extensions = [
            'ext-intl',
            'ext-sqlite3',
            'ext-fileinfo',
            'ext-json',
            'ext-mbstring',
            'ext-ctype',
        ];

        $extensionStatuses = [];

        foreach ($extensions as $extension) {
            $extensionName = str_starts_with($extension, 'ext-') ? substr($extension, 4) : $extension;
            $extensionStatuses[$extension] = \extension_loaded($extensionName);
        }

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
            'extensions' => $extensionStatuses,
            'sqlite' => $sqliteReport,
            'rewrite' => $this->gatherRewriteDiagnostics(),
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
}
