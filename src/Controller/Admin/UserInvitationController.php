<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\Security\InvitationCreateType;
use App\Security\User\AppUser;
use App\Security\User\UserInvitationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class UserInvitationController extends AbstractController
{
    public function __construct(
        private readonly UserInvitationManager $invitationManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/admin/users/invitations', name: 'admin_user_invitations', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $form = $this->createForm(InvitationCreateType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $email = (string) $form->get('email')->getData();
                $invitation = $this->invitationManager->create($email, $this->getAppUserId(), [
                    'initiator' => $this->getUser()?->getUserIdentifier(),
                ]);

                $acceptUrl = $this->urlGenerator->generate('app_invitation_accept', [
                    'token' => $invitation->token,
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $message = (new Email())
                    ->to($invitation->email)
                    ->subject('You are invited to aavion Studio')
                    ->text($this->renderView('emails/user_invitation.txt.twig', [
                        'accept_url' => $acceptUrl,
                    ]));

                $this->mailer->send($message);
                $this->addFlash('success', sprintf('Invitation sent to %s.', $invitation->email));

                return $this->redirectToRoute('admin_user_invitations');
            }

            $form->addError(new FormError('Please provide a valid email address.'));
        }

        $invitations = $this->invitationManager->list();

        return $this->render('admin/users/invitations/index.html.twig', [
            'createForm' => $form->createView(),
            'invitations' => $invitations,
        ]);
    }

    #[Route('/admin/users/invitations/{id}/cancel', name: 'admin_user_invitations_cancel', methods: ['POST'])]
    public function cancel(string $id, Request $request): RedirectResponse
    {
        $token = new CsrfToken('cancel_invite_'.$id, (string) $request->request->get('_token'));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_user_invitations');
        }

        $this->invitationManager->cancel($id, $this->getAppUserId());
        $this->addFlash('success', 'Invitation cancelled.');

        return $this->redirectToRoute('admin_user_invitations');
    }

    private function getAppUserId(): ?string
    {
        $user = $this->getUser();

        if ($user instanceof AppUser) {
            return $user->getId();
        }

        return null;
    }
}
