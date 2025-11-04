<?php

declare(strict_types=1);

namespace App\Setup;

use App\Installer\DefaultSystemSettings;
use App\Settings\SystemSettings;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;
use Symfony\Component\Uid\Ulid;

final class SetupConfigurator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SetupConfiguration $configuration,
        private readonly ?SystemSettings $runtimeSystemSettings = null,
    ) {
    }

    public function apply(): void
    {
        $this->applyFromData(
            $this->configuration->getSystemSettings(),
            $this->configuration->getProjects()
        );
    }

    /**
     * @param array<string, mixed>              $settings
     * @param list<array<string, mixed>>        $projects
     */
    public function applyFromData(array $settings, array $projects): void
    {
        $settings['core.installer.completed'] = false;

        $this->connection->transactional(function (Connection $connection) use ($settings, $projects): void {
            $this->persistSystemSettings($connection, $settings);
            $this->persistProjects($connection, $projects, $settings);
        });

        $this->runtimeSystemSettings?->reload();
    }

    public function markCompleted(): void
    {
        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->upsertSetting('core.installer.completed', true, $timestamp);
        $this->runtimeSystemSettings?->reload();
        $this->configuration->clear();
    }

    private function persistSystemSettings(Connection $connection, array $settings): void
    {
        if (! $this->tableExists('app_system_setting')) {
            return;
        }

        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($settings as $key => $value) {
            if (!\is_string($key) || $key === '') {
                continue;
            }

            $this->upsertSetting($key, $value, $timestamp);
        }
    }

    private function persistProjects(Connection $connection, array $projects, array $settings): void
    {
        $projectTable = 'user_brain.app_project';

        if (! $this->tableExists($projectTable)) {
            return;
        }

        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $defaultLocale = (string) ($settings['core.locale'] ?? DefaultSystemSettings::all()['core.locale'] ?? 'en');
        $defaultTimezone = (string) ($settings['core.timezone'] ?? DefaultSystemSettings::all()['core.timezone'] ?? 'UTC');

        foreach ($projects as $project) {
            if (!\is_array($project) || !\is_string($project['slug'] ?? null) || $project['slug'] === '') {
                continue;
            }

            $slug = $project['slug'];
            $name = \is_string($project['name'] ?? null) ? $project['name'] : ucfirst($slug);
            $locale = \is_string($project['locale'] ?? null) && $project['locale'] !== '' ? $project['locale'] : $defaultLocale;
            $timezone = \is_string($project['timezone'] ?? null) && $project['timezone'] !== '' ? $project['timezone'] : $defaultTimezone;
            $projectSettings = \is_array($project['settings'] ?? null) ? $project['settings'] : [];

            if (!isset($projectSettings['errors']) || !\is_array($projectSettings['errors'])) {
                $projectSettings['errors'] = [];
            }

            $settingsJson = $this->encodeValue($projectSettings);

            $row = $this->fetchUserProjectBySlug($connection, $slug);

            if ($row === false) {
                $this->insertUserProject($connection, [
                    'id' => (new Ulid())->toBase32(),
                    'slug' => $slug,
                    'name' => $name,
                    'locale' => $locale,
                    'timezone' => $timezone,
                    'settings' => $settingsJson,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            } else {
                $this->updateUserProject($connection, (string) $row['id'], [
                    'name' => $name,
                    'locale' => $locale,
                    'timezone' => $timezone,
                    'settings' => $settingsJson,
                    'updated_at' => $timestamp,
                ]);
            }
        }
    }

    private function upsertSetting(string $key, mixed $value, string $timestamp): void
    {
        $type = $this->determineType($value);
        $encoded = $this->encodeValue($value);

        try {
            $existing = $this->connection->fetchAssociative('SELECT key FROM app_system_setting WHERE key = :key', ['key' => $key]);
        } catch (DBALException) {
            $existing = false;
        }

        try {
            if ($existing === false) {
                $this->connection->insert('app_system_setting', [
                    'key' => $key,
                    'value' => $encoded,
                    'type' => $type,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            } else {
                $this->connection->update('app_system_setting', [
                    'value' => $encoded,
                    'type' => $type,
                    'updated_at' => $timestamp,
                ], ['key' => $key]);
            }
        } catch (DBALException) {
            // schema not yet ready
        }
    }

    private function determineType(mixed $value): string
    {
        $type = \gettype($value);

        return $type === 'double' ? 'float' : $type;
    }

    private function encodeValue(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException(sprintf('Unable to encode system setting: %s', $exception->getMessage()), 0, $exception);
        }
    }

    private function tableExists(string $table): bool
    {
        if (str_contains($table, '.')) {
            return $this->attachedTableExists($table);
        }

        try {
            return $this->connection->createSchemaManager()->tablesExist([$table]);
        } catch (\Throwable) {
            return false;
        }
    }

    private function attachedTableExists(string $table): bool
    {
        [$schema, $name] = explode('.', $table, 2);

        try {
            $result = $this->connection->fetchOne(sprintf(
                "SELECT name FROM %s.sqlite_master WHERE type = 'table' AND name = :name",
                $schema
            ), ['name' => $name]);
        } catch (DBALException) {
            return false;
        }

        return $result !== false && $result !== null;
    }

    private function fetchUserProjectBySlug(Connection $connection, string $slug): array|false
    {
        try {
            return $connection->fetchAssociative(
                'SELECT id FROM user_brain.app_project WHERE slug = :slug',
                ['slug' => $slug]
            );
        } catch (DBALException) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertUserProject(Connection $connection, array $data): void
    {
        try {
            $connection->executeStatement(
                'INSERT INTO user_brain.app_project (id, slug, name, locale, timezone, settings, created_at, updated_at)
                 VALUES (:id, :slug, :name, :locale, :timezone, :settings, :created_at, :updated_at)',
                $data
            );
        } catch (DBALException) {
            // schema not yet ready
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateUserProject(Connection $connection, string $projectId, array $data): void
    {
        $data['id'] = $projectId;

        try {
            $connection->executeStatement(
                'UPDATE user_brain.app_project
                 SET name = :name,
                     locale = :locale,
                     timezone = :timezone,
                     settings = :settings,
                     updated_at = :updated_at
                 WHERE id = :id',
                $data
            );
        } catch (DBALException) {
            // schema not yet ready
        }
    }
}
