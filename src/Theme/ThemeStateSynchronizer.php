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
            $existing = $this->connection->fetchAssociative(
                'SELECT enabled FROM app_theme_state WHERE name = :name',
                ['name' => $manifest->slug],
            );

            if ($existing === false) {
                $this->connection->insert('app_theme_state', [
                    'name' => $manifest->slug,
                    'enabled' => 1,
                    'metadata' => json_encode($manifest->metadata, JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                ]);

                continue;
            }

            $this->connection->update(
                'app_theme_state',
                [
                    'metadata' => json_encode($manifest->metadata, JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                ],
                ['name' => $manifest->slug],
            );
        }
    }
}
