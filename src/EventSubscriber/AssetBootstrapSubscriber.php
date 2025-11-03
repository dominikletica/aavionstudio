<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\AssetRebuildScheduler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AssetBootstrapSubscriber implements EventSubscriberInterface
{
    private bool $checked = false;

    public function __construct(
        private readonly AssetRebuildScheduler $assetRebuildScheduler,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        if ($this->checked) {
            return;
        }

        if (\PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg') {
            return;
        }

        if (! $this->needsAssetRebuild()) {
            $this->checked = true;

            return;
        }

        try {
            $this->assetRebuildScheduler->runNow(force: true);
        } catch (\Throwable $exception) {
            $this->logger?->error('Automatic asset rebuild failed.', [
                'exception' => $exception,
            ]);

            throw $exception;
        } finally {
            $this->checked = true;
        }
    }

    /**
     * @return array<string, callable|string|array{0: callable|string, 1?: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
        ];
    }

    private function needsAssetRebuild(): bool
    {
        $assetsDir = $this->projectDir.'/public/assets';

        if (!is_dir($assetsDir)) {
            return true;
        }

        $iterator = new \FilesystemIterator($assetsDir, \FilesystemIterator::SKIP_DOTS);
        $hasRealEntries = false;

        foreach ($iterator as $fileInfo) {
            $filename = $fileInfo->getFilename();

            if ($filename === '.gitignore' || $filename === '.gitkeep') {
                continue;
            }

            $hasRealEntries = true;
            break;
        }

        if (! $hasRealEntries) {
            return true;
        }

        if (!is_file($assetsDir.'/entrypoint.app.json')) {
            return true;
        }

        $cssFiles = glob($assetsDir.'/styles/app-*.css') ?: [];
        $jsFiles = glob($assetsDir.'/app-*.js') ?: [];

        if (empty($cssFiles) || empty($jsFiles)) {
            return true;
        }

        return false;
    }
}
