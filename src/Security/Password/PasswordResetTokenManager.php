<?php

declare(strict_types=1);

namespace App\Security\Password;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

final class PasswordResetTokenManager
{
    public function __construct(
        private readonly Connection $connection,
        private readonly int $ttlSeconds = 3600,
    ) {
    }

    public function create(string $userId, array $metadata = []): PasswordResetToken
    {
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify(sprintf('+%d seconds', $this->ttlSeconds));

        $id = (new Ulid())->toBase32();
        $selector = $this->generateSelector();
        $verifier = $this->generateVerifier();
        $verifierHash = $this->hashVerifier($verifier);

        $this->connection->insert('app_password_reset_token', [
            'id' => $id,
            'user_id' => $userId,
            'selector' => $selector,
            'verifier_hash' => $verifierHash,
            'requested_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'consumed_at' => null,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);

        return new PasswordResetToken(
            id: $id,
            userId: $userId,
            selector: $selector,
            verifier: $verifier,
            requestedAt: $now,
            expiresAt: $expiresAt,
            consumedAt: null,
            metadata: $metadata,
        );
    }

    public function validate(string $selector, string $verifier): ?PasswordResetToken
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM app_password_reset_token WHERE selector = :selector',
            ['selector' => $selector]
        );

        if ($row === false) {
            return null;
        }

        if (!hash_equals((string) $row['verifier_hash'], $this->hashVerifier($verifier))) {
            return null;
        }

        $requestedAt = new \DateTimeImmutable((string) $row['requested_at']);
        $expiresAt = new \DateTimeImmutable((string) $row['expires_at']);
        $consumedAt = null;

        if (!empty($row['consumed_at'])) {
            $consumedAt = new \DateTimeImmutable((string) $row['consumed_at']);
        }

        $metadata = [];

        if (!empty($row['metadata'])) {
            $decoded = json_decode((string) $row['metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        return new PasswordResetToken(
            id: (string) $row['id'],
            userId: (string) $row['user_id'],
            selector: (string) $row['selector'],
            verifier: $verifier,
            requestedAt: $requestedAt,
            expiresAt: $expiresAt,
            consumedAt: $consumedAt,
            metadata: $metadata,
        );
    }

    public function consume(string $selector): void
    {
        $this->connection->update(
            'app_password_reset_token',
            [
                'consumed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
            [
                'selector' => $selector,
            ]
        );
    }

    public function purgeExpired(\DateTimeImmutable $threshold = new \DateTimeImmutable()): int
    {
        return $this->connection->executeStatement(
            'DELETE FROM app_password_reset_token WHERE expires_at <= :threshold',
            [
                'threshold' => $threshold->format('Y-m-d H:i:s'),
            ]
        );
    }

    private function generateSelector(): string
    {
        return bin2hex(random_bytes(12));
    }

    private function generateVerifier(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function hashVerifier(string $verifier): string
    {
        return hash('sha256', $verifier);
    }
}
