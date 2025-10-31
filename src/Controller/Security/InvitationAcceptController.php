<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Form\Security\InvitationAcceptType;
use App\Security\User\UserCreator;
use App\Security\User\UserInvitationManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class InvitationAcceptController extends AbstractController
{
    public function __construct(
        private readonly UserInvitationManager $invitationManager,
        private readonly UserCreator $userCreator,
    ) {
    }

    #[Route('/invite/{token}', name: 'app_invitation_accept', methods: ['GET', 'POST'])]
    public function __invoke(string $token, Request $request): Response
    {
        $invitation = $this->invitationManager->findPendingByToken($token);

        if ($invitation === null) {
            $this->addFlash('error', 'Invitation is invalid or has expired.');

            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(InvitationAcceptType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();

                try {
                    $this->userCreator->create(
                        $invitation->email,
                        $data['display_name'],
                        $data['plainPassword'],
                        roles: ['ROLE_VIEWER'],
                        locale: 'en',
                        timezone: 'UTC',
                        flags: [],
                        createdBy: $invitation->invitedBy !== '' ? $invitation->invitedBy : null
                    );
                } catch (UniqueConstraintViolationException $exception) {
                    $form->addError(new FormError('An account with this email already exists.'));
                    goto render;
                }

                $this->invitationManager->accept($token);

                $this->addFlash('success', 'Account created. You can now sign in.');

                return $this->redirectToRoute('app_login');
            }

            $form->addError(new FormError('Please fix the highlighted errors.'));
        }

        render:

        return $this->render('invitation/accept.html.twig', [
            'acceptForm' => $form->createView(),
            'email' => $invitation->email,
        ]);
    }
}
