<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Security;

use App\Shared\Infrastructure\Security\AuthenticatedActor;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PasteListVoter extends Voter
{
    public const LIST_OWN = 'PASTE_LIST_OWN';
    public const AUDIT_READ = 'PASTE_AUDIT_READ';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::LIST_OWN, self::AUDIT_READ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof AuthenticatedActor && $user->hasPermission(match ($attribute) {
            self::LIST_OWN => PastePermissions::LIST_OWN,
            self::AUDIT_READ => PastePermissions::AUDIT_READ,
        });
    }
}