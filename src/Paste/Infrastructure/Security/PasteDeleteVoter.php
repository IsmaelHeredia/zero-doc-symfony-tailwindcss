<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Security;

use App\Shared\Infrastructure\Security\AuthenticatedActor;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PasteDeleteVoter extends Voter
{
    public const OWNER = 'PASTE_DELETE_OWNER';
    public const MODERATOR = 'PASTE_DELETE_ANY';
    public const TOKEN = 'PASTE_DELETE_TOKEN';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof PasteDeletionRequest
            && \in_array($attribute, [self::OWNER, self::MODERATOR, self::TOKEN], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        \assert($subject instanceof PasteDeletionRequest);
        $user = $token->getUser();

        return match ($attribute) {
            self::OWNER => $user instanceof AuthenticatedActor && $subject->paste->isOwnedBy($user->actorId()),
            self::MODERATOR => $user instanceof AuthenticatedActor && $user->hasPermission(PastePermissions::DELETE_ANY),
            self::TOKEN => null !== $subject->token && $subject->paste->acceptsDeleteToken($subject->token),
            default => false,
        };
    }
}