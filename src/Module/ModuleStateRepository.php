<?php

declare(strict_types=1);

namespace App\Module;

use Doctrine\DBAL\Connection;

final class ModuleStateRepository
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

        if (!$schemaManager->tablesExist(['app_module_state'])) {
            return null;
        }

        $row = $this->connection->fetchAssociative('SELECT enabled, metadata FROM app_module_state WHERE name = :name', [
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
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array<string, array{enabled: bool, metadata: array<string, mixed>}>|null
     */
    public function all(): ?array
    {
        try {
            $schemaManager = $this->connection->createSchemaManager();
        } catch (\Throwable) {
            return null;
        }

        if (!$schemaManager->tablesExist(['app_module_state'])) {
            return null;
        }

        $rows = $this->connection->fetchAllAssociative('SELECT name, enabled, metadata FROM app_module_state');

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
                'metadata' => $metadata,
            ];
        }

        return $states;
    }

    public function setEnabled(string $slug, bool $enabled): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $existing = $this->connection->fetchAssociative('SELECT metadata FROM app_module_state WHERE name = :name', ['name' => $slug]);
        $metadata = [];
        if ($existing !== false && isset($existing['metadata'])) {
            $decoded = json_decode((string) $existing['metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $this->connection->executeStatement(
            'INSERT INTO app_module_state (name, enabled, metadata, updated_at) VALUES (:name, :enabled, :metadata, :updated_at)
            ON CONFLICT(name) DO UPDATE SET enabled = excluded.enabled, updated_at = excluded.updated_at',
            [
                'name' => $slug,
                'enabled' => $enabled ? 1 : 0,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]
        );
    }
}
