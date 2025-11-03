<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Setup\SetupState;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SetupRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SetupState $setupState,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        if ($this->setupState->isCompleted()) {
            return;
        }

        $request = $event->getRequest();

        $path = $request->getPathInfo();

        if (\str_starts_with($path, '/setup')) {
            return;
        }

        if (\str_starts_with($path, '/_')) {
            return;
        }

        if (\str_starts_with($path, '/_error')) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_installer')));
    }

    /**
     * @return array<string, callable|string|array{0: callable|string, 1?: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
        ];
    }
}
