<?php

declare(strict_types=1);

namespace App\Security\Audit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Provides read access to the security audit log.
 */
final class SecurityAuditRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<array{
     *     id: string,
     *     action: string,
     *     occurred_at: \DateTimeImmutable,
     *     actor_id: ?string,
     *     actor_email: ?string,
     *     actor_name: ?string,
     *     subject_id: ?string,
     *     context: array<string, mixed>
     * }>
     */
    public function search(?string $action = null, ?string $actor = null, ?string $subject = null, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, int $limit = 200): array
    {
        $conditions = [];
        $params = [];
        $types = [];

        if ($action !== null && $action !== '') {
            $conditions[] = 'al.action LIKE :action';
            $params['action'] = $action.(str_ends_with($action, '%') ? '' : '%');
        }

        if ($actor !== null && $actor !== '') {
            $conditions[] = '(LOWER(actor.email) LIKE :actor OR al.actor_id = :actor_id)';
            $params['actor'] = '%'.mb_strtolower($actor).'%';
            $params['actor_id'] = $actor;
        }

        if ($subject !== null && $subject !== '') {
            $conditions[] = 'al.subject_id = :subject';
            $params['subject'] = $subject;
        }

        if ($from !== null) {
            $conditions[] = 'al.occurred_at >= :from';
            $params['from'] = $from->format('Y-m-d H:i:s');
        }

        if ($to !== null) {
            $conditions[] = 'al.occurred_at <= :to';
            $params['to'] = $to->format('Y-m-d H:i:s');
        }

        $sql = <<<SQL
            SELECT
                al.id,
                al.action,
                al.context,
                al.occurred_at,
                al.actor_id,
                actor.email AS actor_email,
                actor.display_name AS actor_name,
                al.subject_id
            FROM app_audit_log al
            LEFT JOIN app_user actor ON actor.id = al.actor_id
        SQL;

        if ($conditions !== []) {
            $sql .= ' WHERE '.implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY al.occurred_at DESC LIMIT :limit';

        $stmt = $this->connection->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue('limit', $limit, ParameterType::INTEGER);

        $result = $stmt->executeQuery();
        $entries = [];

        while ($row = $result->fetchAssociative()) {
            $entries[] = [
                'id' => (string) $row['id'],
                'action' => (string) $row['action'],
                'occurred_at' => $this->parseDate((string) $row['occurred_at']),
                'actor_id' => $row['actor_id'] !== null ? (string) $row['actor_id'] : null,
                'actor_email' => $row['actor_email'] !== null ? (string) $row['actor_email'] : null,
                'actor_name' => $row['actor_name'] !== null ? (string) $row['actor_name'] : null,
                'subject_id' => $row['subject_id'] !== null ? (string) $row['subject_id'] : null,
                'context' => $this->decodeContext((string) $row['context']),
            ];
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    public function getAvailableActions(): array
    {
        $rows = $this->connection->fetchFirstColumn('SELECT DISTINCT action FROM app_audit_log ORDER BY action ASC');

        return array_map(static fn ($action): string => (string) $action, $rows);
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeContext(string $json): array
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['raw' => $json];
        }

        return is_array($data) ? $data : ['raw' => $json];
    }
}
