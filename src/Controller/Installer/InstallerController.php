<?php

declare(strict_types=1);

namespace App\Controller\Installer;

use App\Doctrine\Health\SqliteHealthChecker;
use App\Installer\DefaultProjects;
use App\Installer\DefaultSystemSettings;
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
        ];
    }
}
