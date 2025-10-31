<?php

declare(strict_types=1);

namespace App\Theme;

use Doctrine\DBAL\Connection;

final class ThemeStateRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function isEnabled(string $slug): bool
    {
        $state = $this->find($slug);

        return $state['enabled'] ?? true;
    }

    /**
     * @return array{enabled: bool, metadata: array<string, mixed>}|null
     */
    public function find(string $slug): ?array
    {
        try {
            $schemaManager = $this->connection->createSchemaManager();
        } catch (\Throwable) {
            return null;
        }

        if (!$schemaManager->tablesExist(['app_theme_state'])) {
            return null;
        }

        $row = $this->connection->fetchAssociative('SELECT enabled, active, metadata FROM app_theme_state WHERE name = :name', [
            'name' => $slug,
        ]);

        if ($row === false) {
            return null;
        }

        $metadata = [];

        if (isset($row['metadata'])) {
            $decoded = json_decode((string) $row['metadata'], true);

            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        return [
            'enabled' => ((int) $row['enabled']) === 1,
            'active' => ((int) $row['active']) === 1,
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array<string, array{enabled: bool, active: bool, metadata: array<string, mixed>}>|null
     */
    public function all(): ?array
    {
        try {
            $schemaManager = $this->connection->createSchemaManager();
        } catch (\Throwable) {
            return null;
        }

        if (!$schemaManager->tablesExist(['app_theme_state'])) {
            return null;
        }

        $rows = $this->connection->fetchAllAssociative('SELECT name, enabled, active, metadata FROM app_theme_state');

        $states = [];

        foreach ($rows as $row) {
            $metadata = [];

            if (isset($row['metadata'])) {
                $decoded = json_decode((string) $row['metadata'], true);

                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }

            $states[(string) $row['name']] = [
                'enabled' => ((int) $row['enabled']) === 1,
                'active' => ((int) $row['active']) === 1,
                'metadata' => $metadata,
            ];
        }

        return $states;
    }

    public function setEnabled(string $slug, bool $enabled): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $existing = $this->connection->fetchAssociative('SELECT metadata, active FROM app_theme_state WHERE name = :name', ['name' => $slug]);
        $metadata = [];
        $active = 0;
        if ($existing !== false && isset($existing['metadata'])) {
            $decoded = json_decode((string) $existing['metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
            $active = (int) ($existing['active'] ?? 0);
        }

        $this->connection->executeStatement(
            'INSERT INTO app_theme_state (name, enabled, active, metadata, updated_at) VALUES (:name, :enabled, :active, :metadata, :updated_at)
            ON CONFLICT(name) DO UPDATE SET enabled = excluded.enabled, updated_at = excluded.updated_at',
            [
                'name' => $slug,
                'enabled' => $enabled ? 1 : 0,
                'active' => $active,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]
        );
    }

    public function activate(string $slug): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->executeStatement('UPDATE app_theme_state SET active = 0, updated_at = :updated_at WHERE active = 1', [
            'updated_at' => $now,
        ]);

        $existing = $this->connection->fetchAssociative('SELECT metadata FROM app_theme_state WHERE name = :name', ['name' => $slug]);
        $metadata = [];
        if ($existing !== false && isset($existing['metadata'])) {
            $decoded = json_decode((string) $existing['metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $this->connection->executeStatement(
            'INSERT INTO app_theme_state (name, enabled, active, metadata, updated_at) VALUES (:name, 1, 1, :metadata, :updated_at)
            ON CONFLICT(name) DO UPDATE SET active = 1, enabled = 1, updated_at = excluded.updated_at',
            [
                'name' => $slug,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]
        );
    }
}
