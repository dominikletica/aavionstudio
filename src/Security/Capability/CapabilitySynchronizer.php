<?php

declare(strict_types=1);

namespace App\Security\Capability;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

final class CapabilitySynchronizer
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CapabilityRegistry $registry,
    ) {
    }

    public function synchronize(): void
    {
        try {
            $schemaManager = $this->connection->createSchemaManager();
        } catch (\Throwable) {
            return;
        }

        $hasRoleCapability = $schemaManager->tablesExist(['app_role_capability']);
        $hasAuditLog = $schemaManager->tablesExist(['app_audit_log']);

        if (!$hasRoleCapability) {
            return;
        }

        $existing = $this->connection->fetchAllAssociative(
            'SELECT role_name, capability FROM app_role_capability'
        );

        $existingSet = [];

        foreach ($existing as $row) {
            $role = (string) ($row['role_name'] ?? '');
            $capability = (string) ($row['capability'] ?? '');

            if ($role === '' || $capability === '') {
                continue;
            }

            $existingSet[$role.'::'.$capability] = true;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($this->registry->all() as $descriptor) {
            foreach ($descriptor->defaultRoles as $role) {
                $key = $role.'::'.$descriptor->key;

                if (isset($existingSet[$key])) {
                    continue;
                }

                $this->connection->insert('app_role_capability', [
                    'role_name' => $role,
                    'capability' => $descriptor->key,
                ]);

                $existingSet[$key] = true;

                if ($hasAuditLog) {
                    $this->connection->insert('app_audit_log', [
                        'id' => (new Ulid())->toBase32(),
                        'actor_id' => null,
                        'action' => 'security.capability.seeded',
                        'subject_id' => null,
                        'context' => json_encode([
                            'role' => $role,
                            'capability' => $descriptor->key,
                            'module' => $descriptor->module,
                        ], JSON_THROW_ON_ERROR),
                        'ip_hash' => null,
                        'occurred_at' => $now,
                    ]);
                }
            }
        }
    }
}
