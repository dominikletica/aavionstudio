<?php

declare(strict_types=1);

namespace App\Security\User;

use App\Security\Audit\SecurityAuditLogger;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

final class UserInvitationManager
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SecurityAuditLogger $auditLogger,
        private readonly int $ttlSeconds = 604800, // 7 days
    ) {
    }

    public function create(string $email, ?string $invitedBy = null, array $metadata = []): UserInvitation
    {
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify(sprintf('+%d seconds', $this->ttlSeconds));
        $id = (new Ulid())->toBase32();
        $token = bin2hex(random_bytes(32));
        $tokenHash = $this->hashToken($token);

        $this->connection->insert('app_user_invitation', [
            'id' => $id,
            'email' => strtolower($email),
            'token_hash' => $tokenHash,
            'status' => 'pending',
            'invited_by' => $invitedBy,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'accepted_at' => null,
            'cancelled_at' => null,
        ]);

        $this->auditLogger->log('user.invitation.created', [
            'email' => $email,
            'invited_by' => $invitedBy,
        ], actorId: $invitedBy);

        return new UserInvitation(
            id: $id,
            email: strtolower($email),
            token: $token,
            status: 'pending',
            invitedBy: $invitedBy ?? '',
            createdAt: $now,
            expiresAt: $expiresAt,
            acceptedAt: null,
            cancelledAt: null,
            metadata: $metadata,
        );
    }

    public function cancel(string $id, ?string $actorId = null): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->update('app_user_invitation', [
            'status' => 'cancelled',
            'cancelled_at' => $now,
        ], [
            'id' => $id,
        ]);

        $this->auditLogger->log('user.invitation.cancelled', [
            'invitation_id' => $id,
        ], actorId: $actorId, subjectId: $id);
    }

    public function accept(string $token): ?UserInvitation
    {
        $invitation = $this->findPendingByToken($token);

        if ($invitation === null) {
            return null;
        }

        $now = new \DateTimeImmutable();

        $this->connection->update('app_user_invitation', [
            'status' => 'accepted',
            'accepted_at' => $now->format('Y-m-d H:i:s'),
        ], [
            'id' => $invitation->id,
        ]);

        $this->auditLogger->log('user.invitation.accepted', [
            'email' => $invitation->email,
        ], actorId: $invitation->id, subjectId: $invitation->id);

        return new UserInvitation(
            id: $invitation->id,
            email: $invitation->email,
            token: $token,
            status: 'accepted',
            invitedBy: $invitation->invitedBy,
            createdAt: $invitation->createdAt,
            expiresAt: $invitation->expiresAt,
            acceptedAt: $now,
            cancelledAt: null,
            metadata: $invitation->metadata,
        );
    }

    public function findPendingByToken(string $token): ?UserInvitation
    {
        $invitation = $this->findByToken($token);

        if ($invitation === null || !$invitation->isPending()) {
            return null;
        }

        return $invitation;
    }

    public function findByToken(string $token): ?UserInvitation
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM app_user_invitation WHERE token_hash = :hash',
            [
                'hash' => $this->hashToken($token),
            ]
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row, $token);
    }

    public function purgeExpired(\DateTimeImmutable $threshold): int
    {
        return $this->connection->executeStatement(
            'DELETE FROM app_user_invitation WHERE status = :status AND expires_at <= :threshold',
            [
                'status' => 'pending',
                'threshold' => $threshold->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * @return list<UserInvitation>
     */
    public function list(?string $status = null, int $limit = 100): array
    {
        $sql = 'SELECT * FROM app_user_invitation';
        $params = [];

        if ($status !== null) {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT '.(int) $limit;

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $result = $stmt->executeQuery();

        $invitations = [];

        while ($row = $result->fetchAssociative()) {
            $invitations[] = $this->hydrate($row, '');
        }

        return $invitations;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row, string $token): UserInvitation
    {
        $metadata = [];
        if (!empty($row['metadata'])) {
            $decoded = json_decode((string) $row['metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        return new UserInvitation(
            id: (string) $row['id'],
            email: (string) $row['email'],
            token: $token,
            status: (string) $row['status'],
            invitedBy: (string) ($row['invited_by'] ?? ''),
            createdAt: new \DateTimeImmutable((string) $row['created_at']),
            expiresAt: new \DateTimeImmutable((string) $row['expires_at']),
            acceptedAt: empty($row['accepted_at']) ? null : new \DateTimeImmutable((string) $row['accepted_at']),
            cancelledAt: empty($row['cancelled_at']) ? null : new \DateTimeImmutable((string) $row['cancelled_at']),
            metadata: $metadata,
        );
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
