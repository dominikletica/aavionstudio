<?php

declare(strict_types=1);

namespace App\Security\Api;

use App\Security\Audit\SecurityAuditLogger;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

final class ApiKeyManager
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SecurityAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param list<string> $scopes
     *
     * @return array{id: string, secret: string, label: string}
     */
    public function issue(string $userId, string $label, array $scopes = [], ?\DateTimeImmutable $expiresAt = null, ?string $actorId = null): array
    {
        $id = (new Ulid())->toBase32();
        $label = trim($label);
        $scopes = $this->normaliseScopes($scopes);

        if ($label === '') {
            throw new \InvalidArgumentException('Label cannot be empty.');
        }

        $secret = $this->generatePlainSecret();
        $hashed = hash('sha512', $secret);
        $now = new \DateTimeImmutable();

        $this->connection->insert('app_api_key', [
            'id' => $id,
            'user_id' => $userId,
            'label' => $label,
            'hashed_key' => $hashed,
            'scopes' => json_encode($scopes, JSON_THROW_ON_ERROR),
            'last_used_at' => null,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'revoked_at' => null,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
        ]);

        $this->auditLogger->log('api.key.issued', [
            'api_key_id' => $id,
            'user_id' => $userId,
            'label' => $label,
            'scopes' => $scopes,
            'expires_at' => $expiresAt?->format(DATE_ATOM),
        ], actorId: $actorId, subjectId: $id);

        return [
            'id' => $id,
            'secret' => $secret,
            'label' => $label,
        ];
    }

    public function revoke(string $apiKeyId, ?string $actorId = null): void
    {
        $now = new \DateTimeImmutable();

        $this->connection->update('app_api_key', [
            'revoked_at' => $now->format('Y-m-d H:i:s'),
        ], [
            'id' => $apiKeyId,
        ]);

        $this->auditLogger->log('api.key.revoked', [
            'api_key_id' => $apiKeyId,
        ], actorId: $actorId, subjectId: $apiKeyId);
    }

    /**
     * @return list<ApiKey>
     */
    public function listForUser(string $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, user_id, label, scopes, created_at, last_used_at, revoked_at, expires_at FROM app_api_key WHERE user_id = :user ORDER BY created_at DESC',
            ['user' => $userId]
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function get(string $id): ?ApiKey
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, label, scopes, created_at, last_used_at, revoked_at, expires_at FROM app_api_key WHERE id = :id',
            ['id' => $id]
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ApiKey
    {
        return new ApiKey(
            id: (string) $row['id'],
            userId: (string) $row['user_id'],
            label: (string) $row['label'],
            scopes: $this->decodeScopes((string) ($row['scopes'] ?? '[]')),
            createdAt: $this->parseDate((string) $row['created_at']),
            lastUsedAt: $this->parseDateOrNull($row['last_used_at'] ?? null),
            expiresAt: $this->parseDateOrNull($row['expires_at'] ?? null),
            revokedAt: $this->parseDateOrNull($row['revoked_at'] ?? null),
        );
    }

    /**
     * @param list<string> $scopes
     *
     * @return list<string>
     */
    private function normaliseScopes(array $scopes): array
    {
        $normalised = array_filter(array_map(
            static fn (string $scope): string => trim($scope),
            $scopes
        ), static fn (string $scope): bool => $scope !== '');

        $unique = array_values(array_unique($normalised));
        sort($unique);

        return $unique;
    }

    private function decodeScopes(string $json): array
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        return $this->normaliseScopes(array_map(static fn ($item): string => (string) $item, $data));
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value);
    }

    private function parseDateOrNull(null|string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function generatePlainSecret(): string
    {
        return sprintf('%s.%s', (new Ulid())->toBase32(), bin2hex(random_bytes(16)));
    }
}
