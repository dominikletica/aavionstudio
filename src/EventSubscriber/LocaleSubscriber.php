<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Internationalization\LocaleProvider;
use App\Settings\SystemSettings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;

final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly LocaleProvider $localeProvider,
        private readonly SystemSettings $systemSettings,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $available = $this->localeProvider->available();
        if ($available === []) {
            $available = [$this->localeProvider->fallback()];
        }

        $locale = $this->resolveUserLocale($available)
            ?? $request->getPreferredLanguage($available)
            ?? $this->resolveSystemLocale($available)
            ?? $this->localeProvider->fallback();

        $request->setLocale($locale);
        $request->attributes->set('_locale', $locale);
        \Locale::setDefault($locale);
    }

    /**
     * @param list<string> $available
     */
    private function resolveUserLocale(array $available): ?string
    {
        $user = $this->security->getUser();
        if ($user === null) {
            return null;
        }

        $candidate = null;
        if (method_exists($user, 'getLocale')) {
            $candidate = (string) $user->getLocale();
        }

        if ($candidate === null || $candidate === '') {
            return null;
        }

        return $this->localeProvider->isSupported($candidate) ? $candidate : null;
    }

    /**
     * @param list<string> $available
     */
    private function resolveSystemLocale(array $available): ?string
    {
        $systemDefault = (string) $this->systemSettings->get('core.locale', '');
        if ($systemDefault === '') {
            return null;
        }

        return $this->localeProvider->isSupported($systemDefault) ? $systemDefault : null;
    }
}
