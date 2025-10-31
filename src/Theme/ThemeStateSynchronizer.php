<?php

declare(strict_types=1);

namespace App\Theme;

use Doctrine\DBAL\Connection;

final class ThemeStateSynchronizer
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param list<ThemeManifest> $manifests
     */
    public function synchronize(array $manifests): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['app_theme_state'])) {
            return;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($manifests as $manifest) {
            $metadata = $manifest->metadata;

            if ($manifest->repository !== null) {
                $metadata['repository'] = $manifest->repository;
            }

            $metadataJson = json_encode($metadata, JSON_THROW_ON_ERROR);
            $locked = (bool) ($metadata['locked'] ?? false);

            $existing = $this->connection->fetchAssociative(
                'SELECT enabled, active FROM app_theme_state WHERE name = :name',
                ['name' => $manifest->slug],
            );

            if ($existing === false) {
                $this->connection->insert('app_theme_state', [
                    'name' => $manifest->slug,
                    'enabled' => $locked ? 1 : (int) $manifest->enabled,
                    'active' => $locked ? 1 : (int) $manifest->active,
                    'metadata' => $metadataJson,
                    'updated_at' => $now,
                ]);

                continue;
            }

            $enabled = $locked ? 1 : (int) $existing['enabled'];
            $active = $locked ? 1 : (int) $existing['active'];

            $this->connection->update(
                'app_theme_state',
                [
                    'enabled' => $enabled,
                    'active' => $active,
                    'metadata' => $metadataJson,
                    'updated_at' => $now,
                ],
                ['name' => $manifest->slug],
            );
        }
    }
}
