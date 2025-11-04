<?php

declare(strict_types=1);

namespace App\Routing;

use App\Settings\SystemSettings;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;

final class RouterDefaultUriSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SystemSettings $systemSettings,
        private readonly RouterInterface $router,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly ?string $defaultUri = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'configureRouterContext',
        ];
    }

    public function configureRouterContext(ConsoleCommandEvent $event): void
    {
        $value = $this->systemSettings->get('core.url');
        $uri = is_string($value) ? $value : '';

        if (! $this->isValidUri($uri)) {
            $uri = $this->defaultUri ?? '';
        }

        if (! $this->isValidUri($uri)) {
            return;
        }

        $this->router->getContext()->fromUri($uri);
    }

    private function isValidUri(?string $uri): bool
    {
        if (!\is_string($uri) || $uri === '') {
            return false;
        }

        return filter_var($uri, FILTER_VALIDATE_URL) !== false;
    }
}
