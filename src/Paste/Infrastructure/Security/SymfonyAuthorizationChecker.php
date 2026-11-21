<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Security;

use App\Paste\Application\Port\AuthorizationChecker;
use App\Paste\Application\Port\CreationRights;
use App\Paste\Application\Port\DeletionGrant;
use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\Paste;
use App\Paste\Domain\ValueObject\DeleteToken;
use App\Shared\Domain\UserId;
use App\Shared\Infrastructure\Security\AuthenticatedActor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class SymfonyAuthorizationChecker implements AuthorizationChecker
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $security,
        private readonly TokenStorageInterface $tokens,
    ) {
    }

    public function creationRights(): CreationRights
    {
        return new CreationRights(
            $this->actor(),
            $this->security->isGranted(PasteCreateVoter::CREATE),
            $this->security->isGranted(PasteCreateVoter::CREATE_PERMANENT),
            $this->security->isGranted(PasteCreateVoter::CREATE_LARGE),
        );
    }

    public function deletionGrantFor(Paste $paste, ?DeleteToken $token): ?DeletionGrant
    {
        $request = new PasteDeletionRequest($paste, $token);
        $actor = $this->actor();

        return match (true) {
            $this->security->isGranted(PasteDeleteVoter::OWNER, $request) => new DeletionGrant(DeletionReason::Owner, $actor),
            $this->security->isGranted(PasteDeleteVoter::TOKEN, $request) => new DeletionGrant(DeletionReason::Token),
            $this->security->isGranted(PasteDeleteVoter::MODERATOR, $request) => new DeletionGrant(DeletionReason::Moderator, $actor),
            default => null,
        };
    }

    private function actor(): ?UserId
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof AuthenticatedActor ? $user->actorId() : null;
    }
}