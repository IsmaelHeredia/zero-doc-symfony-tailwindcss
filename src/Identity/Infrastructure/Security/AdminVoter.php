<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Infrastructure\Security\AuthenticatedActor;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class AdminVoter extends Voter
{
    public const USERS = 'ADMIN_USERS';
    public const ROLES = 'ADMIN_ROLES';
    public const AUDIT = 'ADMIN_AUDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::USERS, self::ROLES, self::AUDIT], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof AuthenticatedActor && $user->hasPermission(match ($attribute) {
            self::USERS => AdminPermissions::USER_MANAGE,
            self::ROLES => AdminPermissions::ROLE_MANAGE,
            self::AUDIT => AdminPermissions::AUDIT_READ,
        });
    }
}