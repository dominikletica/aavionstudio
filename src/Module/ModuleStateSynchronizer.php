<?php

declare(strict_types=1);

namespace App\Module;

use Doctrine\DBAL\Connection;

final class ModuleStateSynchronizer
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param list<ModuleManifest> $manifests
     */
    public function synchronize(array $manifests): void
    {
        try {
            $schemaManager = $this->connection->createSchemaManager();
        } catch (\Throwable) {
            return;
        }

        if (!$schemaManager->tablesExist(['app_module_state'])) {
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

            $existing = $this->connection->fetchAssociative('SELECT enabled FROM app_module_state WHERE name = :name', [
                'name' => $manifest->slug,
            ]);

            if ($existing === false) {
                $this->connection->insert('app_module_state', [
                    'name' => $manifest->slug,
                    'enabled' => $locked ? 1 : (int) $manifest->enabled,
                    'metadata' => $metadataJson,
                    'updated_at' => $now,
                ]);

                continue;
            }

            $enabled = $locked ? 1 : (int) $existing['enabled'];

            $this->connection->update('app_module_state', [
                'enabled' => $enabled,
                'metadata' => $metadataJson,
                'updated_at' => $now,
            ], [
                'name' => $manifest->slug,
            ]);
        }
    }
}
