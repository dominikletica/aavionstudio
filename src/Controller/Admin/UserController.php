<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\Security\ApiKeyCreateType;
use App\Form\Security\UserProfileType;
use App\Security\Api\ApiKeyManager;
use App\Security\User\AppUser;
use App\Security\User\UserAdminManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserAdminManager $userAdminManager,
        private readonly ApiKeyManager $apiKeyManager,
    ) {
    }

    #[Route('/admin/users', name: 'admin_users_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', '');

        $users = $this->userAdminManager->listUsers(
            $query !== '' ? $query : null,
            $status !== '' ? $status : null
        );

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'query' => $query,
            'status' => $status,
        ]);
    }

    #[Route('/admin/users/{id}', name: 'admin_users_edit', requirements: ['id' => '[0-9A-Z]{26}'], methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request): Response
    {
        $user = $this->userAdminManager->getUser($id);

        if ($user === null) {
            throw $this->createNotFoundException('User not found.');
        }

        $roleChoices = $this->userAdminManager->getRoleChoices();

        $profileForm = $this->createForm(UserProfileType::class, [
            'display_name' => $user['display_name'],
            'locale' => $user['locale'],
            'timezone' => $user['timezone'],
            'status' => $user['status'],
            'roles' => $user['roles'] !== [] ? $user['roles'] : ['ROLE_VIEWER'],
        ], [
            'role_choices' => $roleChoices,
        ]);

        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted()) {
            if ($profileForm->isValid()) {
                $data = $profileForm->getData();

                $roles = array_map(static fn ($role): string => (string) $role, $data['roles'] ?? []);

                $this->userAdminManager->updateUser($id, [
                    'display_name' => (string) $data['display_name'],
                    'locale' => $data['locale'] !== '' ? (string) $data['locale'] : 'en',
                    'timezone' => $data['timezone'] !== '' ? (string) $data['timezone'] : 'UTC',
                    'status' => (string) $data['status'],
                ], $roles, $this->getActorId());

                $this->addFlash('success', 'User updated.');

                return $this->redirectToRoute('admin_users_edit', ['id' => $id]);
            }

            $profileForm->addError(new FormError('Please review the highlighted fields.'));
        }

        $apiKeyForm = $this->createForm(ApiKeyCreateType::class);
        $apiKeyForm->handleRequest($request);

        if ($apiKeyForm->isSubmitted()) {
            if ($apiKeyForm->isValid()) {
                $data = $apiKeyForm->getData();

                $expiresAt = null;
                if (!empty($data['expires_at'])) {
                    try {
                        $expiresAt = new \DateTimeImmutable((string) $data['expires_at']);
                    } catch (\Exception $exception) {
                        $apiKeyForm->get('expires_at')->addError(new FormError('Invalid date/time format.'));

                        return $this->render('admin/users/edit.html.twig', [
                            'form' => $profileForm->createView(),
                            'user' => $user,
                            'apiKeyForm' => $apiKeyForm->createView(),
                            'apiKeys' => $this->apiKeyManager->listForUser($id),
                        ]);
                    }
                }

                $apiKey = $this->apiKeyManager->issue(
                    $id,
                    (string) $data['label'],
                    $this->parseScopes((string) ($data['scopes'] ?? '')),
                    $expiresAt,
                    $this->getActorId()
                );

                $this->addFlash('success', sprintf('API key "%s" created. Secret: %s (copy now).', $apiKey['label'], $apiKey['secret']));

                return $this->redirectToRoute('admin_users_edit', ['id' => $id]);
            }

            $apiKeyForm->addError(new FormError('Please review the API key form.'));
        }

        $apiKeys = $this->apiKeyManager->listForUser($id);

        return $this->render('admin/users/edit.html.twig', [
            'form' => $profileForm->createView(),
            'user' => $user,
            'apiKeyForm' => $apiKeyForm->createView(),
            'apiKeys' => $apiKeys,
        ]);
    }

    #[Route('/admin/users/{userId}/api-keys/{apiKeyId}/revoke', name: 'admin_users_api_keys_revoke', requirements: ['userId' => '[0-9A-Z]{26}', 'apiKeyId' => '[0-9A-Z]{26}'], methods: ['POST'])]
    public function revokeApiKey(string $userId, string $apiKeyId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('revoke_api_key_'.$apiKeyId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token for API key revoke.');

            return $this->redirectToRoute('admin_users_edit', ['id' => $userId]);
        }

        $apiKey = $this->apiKeyManager->get($apiKeyId);

        if ($apiKey === null || $apiKey->userId !== $userId) {
            $this->addFlash('error', 'API key not found.');

            return $this->redirectToRoute('admin_users_edit', ['id' => $userId]);
        }

        $this->apiKeyManager->revoke($apiKeyId, $this->getActorId());
        $this->addFlash('success', sprintf('API key "%s" revoked.', $apiKey->label));

        return $this->redirectToRoute('admin_users_edit', ['id' => $userId]);
    }

    private function getActorId(): ?string
    {
        $user = $this->getUser();

        if ($user instanceof AppUser) {
            return $user->getId();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function parseScopes(string $scopes): array
    {
        $parts = preg_split('/[\s,]+/', $scopes) ?: [];
        $parts = array_filter(array_map(static fn (string $scope): string => trim($scope), $parts), static fn (string $scope): bool => $scope !== '');

        $unique = array_values(array_unique($parts));
        sort($unique);

        return $unique;
    }
}
