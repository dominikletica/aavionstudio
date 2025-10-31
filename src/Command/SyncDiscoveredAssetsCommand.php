<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\ModuleRegistry;
use App\Theme\ThemeRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:assets:sync',
    description: 'Synchronise module and theme asset directories into the core AssetMapper tree.',
)]
final class SyncDiscoveredAssetsCommand extends Command
{
    private const OPTION_AFTER_CACHE_CLEAR = 'after-cache-clear';
    private const COMMAND_TIMEOUT = 300;

    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ThemeRegistry $themeRegistry,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
        #[Autowire('%kernel.debug%')] private readonly bool $debug,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            self::OPTION_AFTER_CACHE_CLEAR,
            null,
            InputOption::VALUE_NONE,
            'Internal flag used to rerun the command after clearing the cache.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        if (!$input->getOption(self::OPTION_AFTER_CACHE_CLEAR)) {
            $this->refreshContainer($input, $io);

            return Command::SUCCESS;
        }

        $assetRoot = $this->projectDir.'/assets';
        $moduleTargetRoot = $assetRoot.'/modules';
        $themeTargetRoot = $assetRoot.'/themes';

        $filesystem->mkdir([$moduleTargetRoot, $themeTargetRoot]);

        $syncedModuleTargets = $this->syncModuleAssets($filesystem, $io, $moduleTargetRoot);
        $syncedThemeTargets = $this->syncThemeAssets($filesystem, $io, $themeTargetRoot);

        $this->cleanupStaleTargets($filesystem, $moduleTargetRoot, $syncedModuleTargets);
        $this->cleanupStaleTargets($filesystem, $themeTargetRoot, $syncedThemeTargets);

        $io->success(\sprintf(
            'Synced %d module asset director%s and %d theme asset director%s.',
            \count($syncedModuleTargets),
            \count($syncedModuleTargets) === 1 ? 'y' : 'ies',
            \count($syncedThemeTargets),
            \count($syncedThemeTargets) === 1 ? 'y' : 'ies',
        ));

        return Command::SUCCESS;
    }

    private function refreshContainer(InputInterface $input, SymfonyStyle $io): void
    {
        $io->comment('Refreshing Symfony cache before syncing assets.');

        $this->runSubProcess(['cache:clear', '--no-warmup'], $input);
        $this->runSubProcess(['app:assets:sync', '--'.self::OPTION_AFTER_CACHE_CLEAR], $input);
    }

    /**
     * @param list<string> $arguments
     */
    private function runSubProcess(array $arguments, InputInterface $input): void
    {
        $command = array_merge(
            [PHP_BINARY, $this->projectDir.'/bin/console'],
            $arguments,
            ['--no-ansi', '--no-interaction'],
        );

        if ($input->getOption('quiet')) {
            $command[] = '--quiet';
        }

        $process = new Process(
            $command,
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

    /**
     * @return list<string>
     */
    private function syncModuleAssets(Filesystem $filesystem, SymfonyStyle $io, string $targetRoot): array
    {
        $syncedTargets = [];

        foreach ($this->moduleRegistry->all() as $manifest) {
            if (!$manifest->enabled) {
                continue;
            }

            $source = $manifest->assetsPath();
            if ($source === null || !is_dir($source)) {
                continue;
            }

            $target = $targetRoot.'/'.$manifest->slug;
            $this->mirrorDirectory($filesystem, $source, $target);
            $syncedTargets[] = $target;

            $io->note(\sprintf('Module "%s" assets mirrored to %s', $manifest->slug, $this->relativePath($target)));
        }

        return $syncedTargets;
    }

    /**
     * @return list<string>
     */
    private function syncThemeAssets(Filesystem $filesystem, SymfonyStyle $io, string $targetRoot): array
    {
        $syncedTargets = [];

        foreach ($this->themeRegistry->all() as $manifest) {
            if (!$manifest->active && $manifest->slug !== 'base') {
                continue;
            }

            $source = $manifest->assetsPath();

            if ($source === null || !is_dir($source)) {
                continue;
            }

            $target = $targetRoot.'/'.$manifest->slug;
            $this->mirrorDirectory($filesystem, $source, $target);
            $syncedTargets[] = $target;

            $io->note(\sprintf('Theme "%s" assets mirrored to %s', $manifest->slug, $this->relativePath($target)));
        }

        return $syncedTargets;
    }

    /**
     * @param list<string> $validTargets
     */
    private function cleanupStaleTargets(Filesystem $filesystem, string $targetRoot, array $validTargets): void
    {
        if (!is_dir($targetRoot)) {
            return;
        }

        $existing = glob($targetRoot.'/*', \GLOB_ONLYDIR) ?: [];
        $validMap = array_flip(array_map(static fn (string $path): string => realpath($path) ?: $path, $validTargets));

        foreach ($existing as $path) {
            $real = realpath($path) ?: $path;
            if (!isset($validMap[$real])) {
                $filesystem->remove($path);
            }
        }
    }

    private function mirrorDirectory(Filesystem $filesystem, string $source, string $target): void
    {
        $filesystem->mkdir($target);
        $filesystem->mirror($source, $target, null, [
            'override' => true,
            'delete' => true,
        ]);
    }

    private function relativePath(string $absolute): string
    {
        $relative = substr($absolute, \strlen($this->projectDir) + 1);

        return str_replace('\\', '/', $relative);
    }
}
