<?php

declare(strict_types=1);

namespace App\Moderation\Infrastructure\Security;

use App\Moderation\Application\Exception\ReportActionNotAllowed;
use App\Moderation\Application\Port\ModerationAuthorization;
use App\Shared\Domain\UserId;
use App\Shared\Infrastructure\Security\AuthenticatedActor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class SymfonyModerationAuthorization implements ModerationAuthorization
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $security,
        private readonly TokenStorageInterface $tokens,
    ) {
    }

    public function reporter(): ?UserId
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof AuthenticatedActor ? $user->actorId() : null;
    }

    public function requireReview(): UserId
    {
        $user = $this->tokens->getToken()?->getUser();

        if (!$user instanceof AuthenticatedActor || !$this->security->isGranted(ModerationVoter::REVIEW)) {
            throw ReportActionNotAllowed::create();
        }

        return $user->actorId();
    }
}