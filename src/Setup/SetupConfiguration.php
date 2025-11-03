<?php

declare(strict_types=1);

namespace App\Setup;

use App\Installer\DefaultProjects;
use App\Installer\DefaultSystemSettings;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Stores installer form selections between wizard steps.
 */
final class SetupConfiguration
{
    private const SESSION_KEY = '_app.setup.configuration';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSystemSettings(): array
    {
        $overrides = $this->getSessionValue('system_settings');

        if (!\is_array($overrides)) {
            $overrides = [];
        }

        return array_replace_recursive(DefaultSystemSettings::all(), $overrides);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getProjects(): array
    {
        $defaults = [];

        foreach (DefaultProjects::all() as $project) {
            if (!isset($project['slug']) || !\is_string($project['slug'])) {
                continue;
            }

            $defaults[$project['slug']] = $project;
        }

        $overrides = $this->getSessionValue('projects');
        if (\is_array($overrides)) {
            foreach ($overrides as $slug => $data) {
                if (!\is_string($slug) || $slug === '' || !\is_array($data)) {
                    continue;
                }

                if (isset($defaults[$slug])) {
                    $defaults[$slug] = array_replace_recursive($defaults[$slug], $data);
                } else {
                    $defaults[$slug] = $data;
                }
            }
        }

        return array_values($defaults);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getProfileFields(): array
    {
        $defaults = DefaultSystemSettings::all()['core.users.profile_fields'] ?? [];
        if (!\is_array($defaults)) {
            $defaults = [];
        }

        $overrides = $this->getSessionValue('profile_fields');
        if (\is_array($overrides)) {
            return array_replace_recursive($defaults, $overrides);
        }

        return $defaults;
    }

    public function rememberSystemSettings(array $settings): void
    {
        $this->setSessionValue('system_settings', $settings);
    }

    public function rememberProjects(array $projects): void
    {
        $this->setSessionValue('projects', $projects);
    }

    public function rememberProfileFields(array $fields): void
    {
        $this->setSessionValue('profile_fields', $fields);
    }

    public function clear(): void
    {
        $session = $this->getSession(false);
        if ($session !== null) {
            $session->remove(self::SESSION_KEY);
        }
    }

    private function getSessionValue(string $key): mixed
    {
        $session = $this->getSession(false);
        if ($session === null) {
            return null;
        }

        $data = $session->get(self::SESSION_KEY);
        if (!\is_array($data)) {
            return null;
        }

        return $data[$key] ?? null;
    }

    private function setSessionValue(string $key, array $value): void
    {
        $session = $this->getSession(true);
        if ($session === null) {
            return;
        }

        $data = $session->get(self::SESSION_KEY, []);
        if (!\is_array($data)) {
            $data = [];
        }

        $data[$key] = $value;
        $session->set(self::SESSION_KEY, $data);
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
}
