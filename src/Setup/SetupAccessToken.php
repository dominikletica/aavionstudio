<?php

declare(strict_types=1);

namespace App\Setup;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class SetupAccessToken
{
    public const SESSION_KEY = '_app.setup.action_token';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function issue(): string
    {
        $session = $this->requireSession(start: true);
        $token = $session->get(self::SESSION_KEY);

        if (!\is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function validate(?string $providedToken): bool
    {
        if ($providedToken === null || $providedToken === '') {
            return false;
        }

        $session = $this->getSession(start: true);
        if ($session === null) {
            return false;
        }

        $expected = $session->get(self::SESSION_KEY);

        return \is_string($expected) && hash_equals($expected, $providedToken);
    }

    public function invalidate(): void
    {
        $session = $this->getSession(start: false);
        if ($session !== null) {
            $session->remove(self::SESSION_KEY);
        }
    }

    private function getSession(bool $start): ?SessionInterface
    {
        $session = $this->requestStack->getSession();

        if ($session === null) {
            return null;
        }

        if ($start && ! $session->isStarted()) {
            $session->start();
        }

        return $session;
    }

    private function requireSession(bool $start): SessionInterface
    {
        $session = $this->getSession($start);
        if ($session === null) {
            throw new \RuntimeException('Session support is required during installer bootstrap.');
        }

        return $session;
    }
}
