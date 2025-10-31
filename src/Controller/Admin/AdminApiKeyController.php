<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\Api\ApiKeyManager;
use App\Security\User\AppUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/api/api-keys', name: 'admin_api_keys_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminApiKeyController extends AbstractController
{
    public function __construct(
        private readonly ApiKeyManager $apiKeyManager,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $userId = (string) $request->query->get('user', '');

        if ($userId === '') {
            return $this->json([
                'error' => 'Parameter "user" is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $keys = $this->apiKeyManager->listForUser($userId);

        return $this->json(array_map(static function ($key): array {
            return [
                'id' => $key->id,
                'label' => $key->label,
                'scopes' => $key->scopes,
                'created_at' => $key->createdAt->format(DATE_ATOM),
                'last_used_at' => $key->lastUsedAt?->format(DATE_ATOM),
                'expires_at' => $key->expiresAt?->format(DATE_ATOM),
                'revoked_at' => $key->revokedAt?->format(DATE_ATOM),
                'active' => $key->isActive(),
            ];
        }, $keys));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $payload = $request->toArray();
        $userId = (string) ($payload['user_id'] ?? '');
        $label = (string) ($payload['label'] ?? '');
        $scopes = $this->parseScopes($payload['scopes'] ?? []);
        $expiresAtInput = $payload['expires_at'] ?? null;

        if ($userId === '' || $label === '') {
            return $this->json([
                'error' => 'Fields "user_id" and "label" are required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $expiresAt = null;
        if (is_string($expiresAtInput) && $expiresAtInput !== '') {
            try {
                $expiresAt = new \DateTimeImmutable($expiresAtInput);
            } catch (\Exception $exception) {
                return $this->json([
                    'error' => sprintf('Invalid expires_at value: %s', $exception->getMessage()),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $apiKey = $this->apiKeyManager->issue($userId, $label, $scopes, $expiresAt, $this->getActorId());

        return $this->json([
            'id' => $apiKey['id'],
            'label' => $apiKey['label'],
            'secret' => $apiKey['secret'],
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'revoke', methods: ['DELETE'])]
    public function revoke(string $id): Response
    {
        $this->apiKeyManager->revoke($id, $this->getActorId());

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param list<string>|string $scopes
     * @return list<string>
     */
    private function parseScopes(array|string $scopes): array
    {
        if (is_array($scopes)) {
            $parts = $scopes;
        } else {
            $parts = preg_split('/[\s,]+/', $scopes) ?: [];
        }

        $parts = array_filter(array_map(static fn (string $scope): string => trim($scope), $parts), static fn (string $scope): bool => $scope !== '');
        $unique = array_values(array_unique($parts));
        sort($unique);

        return $unique;
    }

    private function getActorId(): ?string
    {
        $user = $this->getUser();

        if ($user instanceof AppUser) {
            return $user->getId();
        }

        return null;
    }
}
