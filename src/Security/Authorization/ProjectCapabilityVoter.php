<?php

declare(strict_types=1);

namespace App\Security\Authorization;

use App\Security\User\AppUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ProjectCapabilityVoter extends Voter
{
    public const ATTRIBUTE = 'PROJECT_CAPABILITY';

    public function __construct(
        private readonly RoleCapabilityResolver $roleCapabilityResolver,
        private readonly ProjectMembershipRepository $membershipRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ATTRIBUTE && $subject instanceof ProjectCapabilityRequirement;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof AppUser) {
            return false;
        }

        \assert($subject instanceof ProjectCapabilityRequirement);

        // Global roles grant capability regardless of project membership.
        if ($this->roleCapabilityResolver->anyRoleHasCapability($user->getRoles(), $subject->capability)) {
            return true;
        }

        $membership = $this->membershipRepository->find($subject->projectId, $user->getId());

        if ($membership === null) {
            return false;
        }

        $permissions = $membership->permissions;

        // Backwards compatibility: direct capability flag => bool true.
        if (isset($permissions[$subject->capability]) && $permissions[$subject->capability] === true) {
            return true;
        }

        // Structured capabilities array.
        if (is_array($permissions['capabilities'] ?? null)) {
            if (in_array($subject->capability, $permissions['capabilities'], true)) {
                return true;
            }
        }

        return $this->roleCapabilityResolver->roleHasCapability($membership->roleName, $subject->capability);
    }
}
