<?php

declare(strict_types=1);

namespace App\Security\Audit;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

final class SecurityAuditLogger
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function log(string $action, array $context = [], ?string $actorId = null, ?string $subjectId = null, ?string $ipHash = null): void
    {
        try {
            $schemaManager = $this->connection->createSchemaManager();
        } catch (\Throwable) {
            return;
        }

        if (!$schemaManager->tablesExist(['app_audit_log'])) {
            return;
        }

        $this->connection->insert('app_audit_log', [
            'id' => (new Ulid())->toBase32(),
            'actor_id' => $actorId,
            'action' => $action,
            'subject_id' => $subjectId,
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
            'ip_hash' => $ipHash,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
