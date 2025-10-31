<?php

declare(strict_types=1);

namespace App\Asset;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AssetPipelineRefresher
{
    private const COMMAND_TIMEOUT = 300;

    public function __construct(
        private readonly AssetStateTracker $stateTracker,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
        private readonly LoggerInterface $logger,
        private readonly StylesheetImportsBuilder $importsBuilder,
    ) {
    }

    /**
     * @return bool True when a rebuild was executed, false when skipped
     */
    public function refresh(bool $force = false): bool
    {
        $state = $this->stateTracker->currentState();

        if (!$force && $this->stateTracker->isUpToDate($state)) {
            $this->logger->info('[assets] Pipeline already up to date, skipping rebuild.');

            return false;
        }

        $this->logger->info('[assets] Starting pipeline rebuild.', [
            'environment' => $this->environment,
            'force' => $force,
        ]);

        try {
            $this->cleanupBuiltAssetOutputs();
            $this->cleanupSyncedAssetTargets();

            $this->runConsoleCommand(['app:assets:sync']);
            $this->importsBuilder->build();
            $this->runConsoleCommand(['importmap:install']);

            $tailwindCommand = ['tailwind:build'];
            if ($this->environment === 'prod') {
                $tailwindCommand[] = '--minify';
            }
            $this->runConsoleCommand($tailwindCommand);

            $this->runConsoleCommand(['asset-map:compile']);
            $this->runConsoleCommand(['cache:warmup']);
        } catch (\Throwable $exception) {
            $this->logger->error('[assets] Pipeline rebuild failed.', [
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->stateTracker->writeState($state);
        $this->logger->info('[assets] Pipeline rebuild completed successfully.');

        return true;
    }

    private function cleanupBuiltAssetOutputs(): void
    {
        $assetsDir = $this->projectDir.'/public/assets';
        if (!is_dir($assetsDir)) {
            return;
        }

        $filesystem = new Filesystem();
        $filesystem->remove($assetsDir);
        $filesystem->mkdir($assetsDir);
    }

    private function cleanupSyncedAssetTargets(): void
    {
        $filesystem = new Filesystem();
        $paths = [
            $this->projectDir.'/assets/modules',
            $this->projectDir.'/assets/themes',
        ];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $contents = glob($path.'/*') ?: [];

            foreach ($contents as $target) {
                $filesystem->remove($target);
            }
        }
    }

    /**
     * @param list<string> $command
     */
    private function runConsoleCommand(array $command): void
    {
        $arguments = array_merge(
            [PHP_BINARY, $this->projectDir.'/bin/console'],
            $command,
            ['--no-ansi', '--no-interaction']
        );

        $process = new Process(
            $arguments,
            $this->projectDir,
            [
                'APP_ENV' => $this->environment,
                'APP_DEBUG' => $this->debug ? '1' : '0',
            ],
        );

        $process->setTimeout(self::COMMAND_TIMEOUT);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
