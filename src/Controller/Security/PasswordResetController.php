<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Form\Security\PasswordResetRequestType;
use App\Form\Security\PasswordResetType;
use App\Security\Audit\SecurityAuditLogger;
use App\Security\Password\PasswordResetTokenManager;
use App\Security\User\AppUserProvider;
use App\Security\User\AppUserRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly AppUserRepository $userRepository,
        private readonly AppUserProvider $userProvider,
        private readonly PasswordResetTokenManager $tokenManager,
        private readonly SecurityAuditLogger $auditLogger,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/password/forgot', name: 'app_password_forgot')]
    public function request(Request $request): Response
    {
        $form = $this->createForm(PasswordResetRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            $user = $this->userRepository->findActiveByEmail($email);

            if ($user !== null) {
                $token = $this->tokenManager->create($user['id'], ['email' => $user['email']]);
                $resetUrl = $this->urlGenerator->generate('app_password_reset', [
                    'selector' => $token->selector,
                    'token' => $token->verifier,
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $message = (new Email())
                    ->subject('Reset your aavion Studio password')
                    ->to($user['email'])
                    ->text($this->renderView('emails/password_reset.txt.twig', [
                        'reset_url' => $resetUrl,
                        'display_name' => $user['display_name'],
                    ]));

                $this->mailer->send($message);

                $this->auditLogger->log('auth.password.reset.requested', [
                    'user_id' => $user['id'],
                    'email' => $user['email'],
                ], actorId: null, subjectId: $user['id']);
            }

            $this->addFlash('success', 'If an account exists for this email, a reset link has been sent.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('pages/security/password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/password/reset/{selector}', name: 'app_password_reset')]
    public function reset(string $selector, Request $request): Response
    {
        $verifier = (string) $request->query->get('token', '');

        if ($verifier === '') {
            $this->addFlash('error', 'The reset link is invalid or has expired.');

            return $this->redirectToRoute('app_password_forgot');
        }

        $token = $this->tokenManager->validate($selector, $verifier);

        if ($token === null || $token->isExpired() || $token->isConsumed()) {
            $this->addFlash('error', 'The reset link is invalid or has expired.');

            return $this->redirectToRoute('app_password_forgot');
        }

        $form = $this->createForm(PasswordResetType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();

            $userRow = $this->userRepository->findById($token->userId);

            if ($userRow === null) {
                $this->addFlash('error', 'Unable to reset password for this account.');

                return $this->redirectToRoute('app_password_forgot');
            }

            $user = $this->userProvider->loadUserByIdentifier($userRow['email']);
            $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);

            $this->userRepository->updatePassword($token->userId, $hashed);
            $this->tokenManager->consume($selector);
            $this->auditLogger->log('auth.password.reset.completed', [
                'user_id' => $token->userId,
            ], actorId: $token->userId, subjectId: $token->userId);

            $this->addFlash('success', 'Password updated. You can now sign in.');

            return $this->redirectToRoute('app_login');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $form->addError(new FormError('Please correct the highlighted errors.'));
        }

        return $this->render('pages/security/password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }
}
