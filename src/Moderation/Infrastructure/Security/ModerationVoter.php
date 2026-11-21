<?php

declare(strict_types=1);

namespace App\Moderation\Infrastructure\Security;

use App\Shared\Infrastructure\Security\AuthenticatedActor;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ModerationVoter extends Voter
{
    public const REVIEW = 'REPORT_REVIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::REVIEW === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof AuthenticatedActor && $user->hasPermission(ModerationPermissions::REPORT_REVIEW);
    }
}