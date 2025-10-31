<?php

declare(strict_types=1);

namespace App\Security\User;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AppUserStatusChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }

        switch ($user->getStatus()) {
            case 'disabled':
                throw new CustomUserMessageAccountStatusException('Your account has been disabled.');
            case 'pending':
                throw new CustomUserMessageAccountStatusException('Your account is awaiting activation.');
            case 'archived':
                throw new CustomUserMessageAccountStatusException('Your account has been archived.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Currently no post-auth checks; placeholder for MFA/lockout hooks.
    }
}
