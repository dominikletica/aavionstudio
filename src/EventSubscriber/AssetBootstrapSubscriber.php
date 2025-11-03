<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\AssetRebuildScheduler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;
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
        if ($this->checked || ! $event->isMainRequest()) {
            return;
        }

        if (\PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg') {
            return;
        }

        $assetsDir = $this->projectDir.'/public/assets';

        if (is_dir($assetsDir) && ! $this->isDirectoryEmpty($assetsDir)) {
            $this->checked = true;

            return;
        }

        $this->checked = true;

        try {
            $this->assetRebuildScheduler->runNow(force: true);
        } catch (\Throwable $exception) {
            $this->logger?->error('Automatic asset rebuild failed.', [
                'exception' => $exception,
            ]);
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

    private function isDirectoryEmpty(string $path): bool
    {
        if (! is_dir($path)) {
            return true;
        }

        $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);

        return ! $iterator->valid();
    }
}
