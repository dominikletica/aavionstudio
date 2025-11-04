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
    private const KEY_SYSTEM_SETTINGS = 'system_settings';
    private const KEY_PROJECTS = 'projects';
    private const KEY_PROFILE_FIELDS = 'profile_fields';
    private const KEY_ENVIRONMENT_OVERRIDES = 'environment_overrides';
    private const KEY_STORAGE = 'storage';
    private const KEY_ADMIN = 'admin';
    private const DEFAULT_STORAGE_ROOT = 'var/storage';
    /** @var array<string, mixed>|null */
    private ?array $snapshot = null;
    private bool $snapshotActive = false;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function freeze(): void
    {
        if ($this->snapshotActive) {
            return;
        }

        $session = $this->getSession(false);
        if ($session === null) {
            $this->snapshot = [];
            $this->snapshotActive = true;

            return;
        }

        if (! $session->isStarted()) {
            $session->start();
        }

        $data = $session->get(self::SESSION_KEY);
        $this->snapshot = \is_array($data) ? $data : [];
        $session->remove(self::SESSION_KEY);
        $this->snapshotActive = true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSystemSettings(): array
    {
        $overrides = $this->getSessionValue(self::KEY_SYSTEM_SETTINGS);

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

        $overrides = $this->getSessionValue(self::KEY_PROJECTS);
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

        $overrides = $this->getSessionValue(self::KEY_PROFILE_FIELDS);
        if (\is_array($overrides)) {
            return array_replace_recursive($defaults, $overrides);
        }

        return $defaults;
    }

    public function rememberSystemSettings(array $settings): void
    {
        $this->setSessionValue(self::KEY_SYSTEM_SETTINGS, $settings);
    }

    public function rememberProjects(array $projects): void
    {
        $this->setSessionValue(self::KEY_PROJECTS, $projects);
    }

    public function rememberProfileFields(array $fields): void
    {
        $this->setSessionValue(self::KEY_PROFILE_FIELDS, $fields);
    }

    /**
     * @return array<string, string>
     */
    public function getEnvironmentOverrides(): array
    {
        $defaults = [
            'APP_ENV' => (string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev'),
            'APP_DEBUG' => (string) ($_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? '1'),
            'APP_SECRET' => '',
        ];

        $overrides = $this->getSessionValue(self::KEY_ENVIRONMENT_OVERRIDES);
        if (!\is_array($overrides)) {
            return $defaults;
        }

        $normalized = [];

        foreach (['APP_ENV', 'APP_DEBUG', 'APP_SECRET'] as $key) {
            $value = $overrides[$key] ?? null;
            if (\is_scalar($value)) {
                $normalized[$key] = (string) $value;
            }
        }

        return array_replace($defaults, $normalized);
    }

    /**
     * @param array<string, scalar|null> $overrides
     */
    public function rememberEnvironmentOverrides(array $overrides): void
    {
        $stored = [];
        foreach (['APP_ENV', 'APP_DEBUG', 'APP_SECRET'] as $key) {
            $value = $overrides[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if (\is_scalar($value)) {
                $stored[$key] = (string) $value;
            }
        }

        $this->setSessionValue(self::KEY_ENVIRONMENT_OVERRIDES, $stored);
    }

    public function hasEnvironmentOverrides(): bool
    {
        $value = $this->getSessionValue(self::KEY_ENVIRONMENT_OVERRIDES);

        return \is_array($value) && $value !== [];
    }

    /**
     * @return array{root: string}
     */
    public function getStorageConfig(): array
    {
        $config = $this->getSessionValue(self::KEY_STORAGE);
        $root = \is_array($config) && \is_string($config['root'] ?? null) && $config['root'] !== ''
            ? $config['root']
            : self::DEFAULT_STORAGE_ROOT;

        return [
            'root' => $root,
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public function rememberStorageConfig(array $config): void
    {
        $root = $config['root'] ?? null;

        $data = [];
        if (\is_string($root) && $root !== '') {
            $data['root'] = $root;
        }

        $this->setSessionValue(self::KEY_STORAGE, $data);
    }

    public function hasStorageConfig(): bool
    {
        $config = $this->getSessionValue(self::KEY_STORAGE);

        return \is_array($config) && isset($config['root']) && \is_string($config['root']) && $config['root'] !== '';
    }

    /**
     * @return array{
     *     email: string,
     *     display_name: string,
     *     password: string,
     *     locale: string,
     *     timezone: string,
     *     require_mfa: bool,
     *     recovery_email: string,
     *     recovery_phone: string|null
     * }
     */
    public function getAdminAccount(): array
    {
        $defaults = [
            'email' => '',
            'display_name' => '',
            'password' => '',
            'locale' => (string) ($this->getSystemSettings()['core.locale'] ?? 'en'),
            'timezone' => (string) ($this->getSystemSettings()['core.timezone'] ?? 'UTC'),
            'require_mfa' => false,
            'recovery_email' => '',
            'recovery_phone' => null,
        ];

        $stored = $this->getSessionValue(self::KEY_ADMIN);
        if (!\is_array($stored)) {
            return $defaults;
        }

        $normalized = $defaults;
        foreach ($defaults as $key => $value) {
            if ($key === 'require_mfa') {
                $normalized[$key] = filter_var($stored[$key] ?? $value, FILTER_VALIDATE_BOOLEAN);
                continue;
            }

            $raw = $stored[$key] ?? $value;
            if (\is_string($raw)) {
                $normalized[$key] = $raw;
            }
        }

        if (isset($stored['password']) && \is_string($stored['password'])) {
            $normalized['password'] = $stored['password'];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $account
     */
    public function rememberAdminAccount(array $account): void
    {
        $data = [];

        foreach (['email', 'display_name', 'password', 'locale', 'timezone', 'recovery_email'] as $key) {
            $value = $account[$key] ?? null;
            if (\is_string($value)) {
                $data[$key] = $value;
            }
        }

        $recoveryPhone = $account['recovery_phone'] ?? null;
        if (\is_string($recoveryPhone) && $recoveryPhone !== '') {
            $data['recovery_phone'] = $recoveryPhone;
        }

        $data['require_mfa'] = filter_var($account['require_mfa'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $this->setSessionValue(self::KEY_ADMIN, $data);
    }

    public function hasAdminAccount(): bool
    {
        $stored = $this->getSessionValue(self::KEY_ADMIN);

        if (!\is_array($stored)) {
            return false;
        }

        $email = (string) ($stored['email'] ?? '');
        $password = (string) ($stored['password'] ?? '');

        return $email !== '' && $password !== '';
    }

    public function clear(): void
    {
        if ($this->snapshotActive) {
            $this->snapshot = null;
            $this->snapshotActive = false;

            return;
        }

        $session = $this->getSession(false);
        if ($session !== null) {
            $session->remove(self::SESSION_KEY);
        }
    }

    private function getSessionValue(string $key): mixed
    {
        if ($this->snapshotActive) {
            return $this->snapshot[$key] ?? null;
        }

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
        if ($this->snapshotActive) {
            if ($this->snapshot === null) {
                $this->snapshot = [];
            }

            $this->snapshot[$key] = $value;

            return;
        }

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
