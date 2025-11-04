<?php

declare(strict_types=1);

namespace App\Controller\Debug;

use App\Internationalization\LocaleProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

final class LocaleController
{
    public function __construct(
        private readonly LocaleProvider $localeProvider,
        #[Autowire('%kernel.debug%')] private readonly bool $isDebug,
    ) {
    }

    #[Route('/_debug/locale', name: 'app_debug_locale', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (! $this->isDebug) {
            return new Response(status: Response::HTTP_NOT_FOUND);
        }

        $target = $request->request->get('_target');
        $redirectUrl = is_string($target) && $target !== '' ? $target : $request->headers->get('referer', '/');

        $mode = (string) $request->request->get('mode', 'auto');
        $session = $request->getSession();
        if (! $session instanceof SessionInterface) {
            return new RedirectResponse($redirectUrl);
        }

        $this->applyMode($session, $mode);

        return new RedirectResponse($redirectUrl);
    }

    private function applyMode(SessionInterface $session, string $mode): void
    {
        $session->remove('_app.translation_override');
        $session->set('_app.translation_debug_keys', false);

        if ($mode === 'auto') {
            return;
        }

        if ($mode === 'keys') {
            $session->set('_app.translation_debug_keys', true);

            return;
        }

        if ($this->localeProvider->isSupported($mode)) {
            $session->set('_app.translation_override', $mode);
        }
    }
}
