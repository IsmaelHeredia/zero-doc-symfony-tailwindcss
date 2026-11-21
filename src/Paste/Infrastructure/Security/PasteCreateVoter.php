<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Security;

use App\Shared\Infrastructure\Security\AuthenticatedActor;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PasteCreateVoter extends Voter
{
    public const CREATE = 'PASTE_CREATE';
    public const CREATE_PERMANENT = 'PASTE_CREATE_PERMANENT';
    public const CREATE_LARGE = 'PASTE_CREATE_LARGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::CREATE, self::CREATE_PERMANENT, self::CREATE_LARGE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof AuthenticatedActor) {
            return self::CREATE === $attribute;
        }

        return $user->hasPermission(match ($attribute) {
            self::CREATE => PastePermissions::CREATE,
            self::CREATE_PERMANENT => PastePermissions::CREATE_PERMANENT,
            self::CREATE_LARGE => PastePermissions::LARGE,
        });
    }
}