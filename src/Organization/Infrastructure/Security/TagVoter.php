<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Security;

use App\Shared\Infrastructure\Security\AuthenticatedActor;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class TagVoter extends Voter
{
    public const MANAGE = 'TAG_MANAGE';
    public const LIST_PASTES = 'TAG_LIST_PASTES';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::MANAGE, self::LIST_PASTES], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof AuthenticatedActor) {
            return false;
        }

        return $user->hasPermission(match ($attribute) {
            self::MANAGE => OrganizationPermissions::TAG_MANAGE_OWN,
            self::LIST_PASTES => OrganizationPermissions::PASTE_LIST_OWN,
        });
    }
}