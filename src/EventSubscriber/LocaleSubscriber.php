<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Internationalization\LocaleProvider;
use App\Settings\SystemSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly LocaleProvider $localeProvider,
        private readonly SystemSettings $systemSettings,
        #[Autowire('%kernel.debug%')] private readonly bool $isDebug,
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

        $request->attributes->set('_available_locales', $available);

        $session = $request->hasSession() ? $request->getSession() : null;

        [$overrideLocale, $debugKeys] = $this->resolveDebugOverrides($session, $available);
        if ($debugKeys) {
            $request->attributes->set('_translation_debug_keys', true);
        }

        $locale = $overrideLocale
            ?? $this->resolveUserLocale($available)
            ?? $request->getPreferredLanguage($available)
            ?? $this->resolveSystemLocale($available)
            ?? $this->localeProvider->fallback();

        $request->setLocale($locale);
        $request->attributes->set('_locale', $locale);
        $request->attributes->set('_debug_locale_mode', $this->determineDebugMode($session, $locale, $debugKeys));
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

    /**
     * @param list<string> $available
     *
     * @return array{0:?string,1:bool}
     */
    private function resolveDebugOverrides(?SessionInterface $session, array $available): array
    {
        if (! $this->isDebug || $session === null) {
            return [null, false];
        }

        $debugKeys = (bool) $session->get('_app.translation_debug_keys', false);
        $override = $session->get('_app.translation_override');

        if (is_string($override) && $override !== '') {
            if ($this->localeProvider->isSupported($override)) {
                return [$override, $debugKeys];
            }
        }

        return [null, $debugKeys];
    }

    private function determineDebugMode(?SessionInterface $session, string $resolvedLocale, bool $debugKeys): string
    {
        if (! $this->isDebug || $session === null) {
            return 'auto';
        }

        if ($debugKeys) {
            return 'keys';
        }

        $override = $session->get('_app.translation_override');
        if (is_string($override) && $override !== '' && $this->localeProvider->isSupported($override)) {
            return $override;
        }

        return 'auto';
    }
}
