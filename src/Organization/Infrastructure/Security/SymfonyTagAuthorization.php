<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Security;

use App\Organization\Application\Exception\TagActionNotAllowed;
use App\Organization\Application\Port\TagAuthorization;
use App\Shared\Domain\UserId;
use App\Shared\Infrastructure\Security\AuthenticatedActor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class SymfonyTagAuthorization implements TagAuthorization
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $security,
        private readonly TokenStorageInterface $tokens,
    ) {
    }

    public function requireManage(): UserId
    {
        return $this->require(TagVoter::MANAGE);
    }

    public function requireListOwnPastes(): UserId
    {
        return $this->require(TagVoter::LIST_PASTES);
    }

    private function require(string $attribute): UserId
    {
        $user = $this->tokens->getToken()?->getUser();

        if (!$user instanceof AuthenticatedActor || !$this->security->isGranted($attribute)) {
            throw TagActionNotAllowed::create();
        }

        return $user->actorId();
    }
}