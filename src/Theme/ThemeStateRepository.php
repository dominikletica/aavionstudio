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

        $row = $this->connection->fetchAssociative('SELECT enabled, metadata FROM app_theme_state WHERE name = :name', [
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

        if (!$schemaManager->tablesExist(['app_theme_state'])) {
            return null;
        }

        $rows = $this->connection->fetchAllAssociative('SELECT name, enabled, metadata FROM app_theme_state');

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
}
