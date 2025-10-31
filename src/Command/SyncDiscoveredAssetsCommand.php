<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\ModuleRegistry;
use App\Theme\ThemeRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:assets:sync',
    description: 'Synchronise module and theme asset directories into the core AssetMapper tree.',
)]
final class SyncDiscoveredAssetsCommand extends Command
{
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ThemeRegistry $themeRegistry,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

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

    /**
     * @return list<string>
     */
    private function syncModuleAssets(Filesystem $filesystem, SymfonyStyle $io, string $targetRoot): array
    {
        $syncedTargets = [];

        foreach ($this->moduleRegistry->all() as $manifest) {
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
