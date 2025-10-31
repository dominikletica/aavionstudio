<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Security\User\UserInvitationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class InvitationAcceptController extends AbstractController
{
    public function __construct(
        private readonly UserInvitationManager $invitationManager,
    ) {
    }

    #[Route('/invite/{token}', name: 'app_invitation_accept', methods: ['GET'])]
    public function accept(string $token): Response
    {
        $invitation = $this->invitationManager->accept($token);

        if ($invitation === null) {
            $this->addFlash('error', 'Invitation is invalid or has expired.');

            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Invitation accepted. An administrator will provide next steps.');

        return $this->redirectToRoute('app_login');
    }
}
