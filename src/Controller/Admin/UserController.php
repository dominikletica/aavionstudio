<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\Security\ApiKeyCreateType;
use App\Form\Security\ProjectMembershipCollectionType;
use App\Form\Security\UserProfileType;
use App\Project\ProjectRepository;
use App\Security\Authorization\ProjectMembershipRepository;
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
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectMembershipRepository $projectMembershipRepository,
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

        $projects = $this->projectRepository->listProjects();
        $projectMemberships = $this->projectMembershipRepository->forUser($id);
        $membershipByProject = [];
        foreach ($projectMemberships as $membership) {
            $membershipByProject[$membership->projectId] = $membership;
        }

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

        $projectAssignmentsData = [
            'assignments' => array_map(function (array $project) use ($membershipByProject): array {
                $membership = $membershipByProject[$project['id']] ?? null;

                return [
                    'project_id' => $project['id'],
                    'role' => $membership?->roleName,
                    'capabilities' => $membership !== null ? implode(', ', $this->extractCapabilities($membership->permissions)) : '',
                ];
            }, $projects),
        ];

        $projectMembershipForm = $this->createForm(ProjectMembershipCollectionType::class, $projectAssignmentsData, [
            'role_choices' => $roleChoices,
        ]);
        $projectMembershipForm->handleRequest($request);

        if ($projectMembershipForm->isSubmitted()) {
            if ($projectMembershipForm->isValid()) {
                /** @var array{assignments: list<array{project_id:string,role:?string,capabilities:?string}>} $data */
                $data = $projectMembershipForm->getData();
                $errors = false;

                foreach ($data['assignments'] as $assignment) {
                    $projectId = (string) $assignment['project_id'];
                    $role = $assignment['role'] ?? '';
                    $capabilities = $this->parseCapabilities((string) ($assignment['capabilities'] ?? ''));

                    if ($role === '' && $capabilities !== []) {
                        $projectMembershipForm->addError(new FormError(sprintf('Select a role for project assignment "%s" before adding capabilities.', $projectId)));
                        $errors = true;
                        continue;
                    }

                    if ($role === '') {
                        $this->projectMembershipRepository->revoke($projectId, $id);
                        continue;
                    }

                    $this->projectMembershipRepository->assign(
                        $projectId,
                        $id,
                        $role,
                        ['capabilities' => $capabilities],
                        $this->getActorId()
                    );
                }

                if (!$errors) {
                    $this->addFlash('success', 'Project memberships updated.');

                    return $this->redirectToRoute('admin_users_edit', ['id' => $id]);
                }
            } else {
                $projectMembershipForm->addError(new FormError('Please review the project assignments.'));
            }
        }

        $apiKeys = $this->apiKeyManager->listForUser($id);

        return $this->render('admin/users/edit.html.twig', [
            'form' => $profileForm->createView(),
            'user' => $user,
            'apiKeyForm' => $apiKeyForm->createView(),
            'apiKeys' => $apiKeys,
            'projectMembershipForm' => $projectMembershipForm->createView(),
            'projects' => $projects,
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

    /**
     * @return list<string>
     */
    private function parseCapabilities(string $capabilities): array
    {
        $parts = preg_split('/[\s,]+/', $capabilities) ?: [];
        $parts = array_filter(array_map(static fn (string $capability): string => trim($capability), $parts), static fn (string $capability): bool => $capability !== '');

        $unique = array_values(array_unique($parts));
        sort($unique);

        return $unique;
    }

    /**
     * @param array<string, mixed> $permissions
     * @return list<string>
     */
    private function extractCapabilities(array $permissions): array
    {
        $capabilities = $permissions['capabilities'] ?? [];

        if (!is_array($capabilities)) {
            return [];
        }

        return array_values(array_map(static fn ($value): string => (string) $value, $capabilities));
    }
}
