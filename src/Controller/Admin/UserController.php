<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\Security\UserProfileType;
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

        $roleChoices = $this->userAdminManager->getRoleChoices();

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'query' => $query,
            'status' => $status,
            'roleChoices' => $roleChoices,
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

        $form = $this->createForm(UserProfileType::class, [
            'display_name' => $user['display_name'],
            'locale' => $user['locale'],
            'timezone' => $user['timezone'],
            'status' => $user['status'],
            'roles' => $user['roles'] !== [] ? $user['roles'] : ['ROLE_VIEWER'],
        ], [
            'role_choices' => $roleChoices,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();

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

            $form->addError(new FormError('Please review the highlighted fields.'));
        }

        return $this->render('admin/users/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
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
