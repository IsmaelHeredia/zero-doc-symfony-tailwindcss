<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Admin\AdminAuthorization;
use App\Identity\Application\Admin\Exception\AdminActionNotAllowed;
use App\Shared\Domain\UserId;
use App\Shared\Infrastructure\Security\AuthenticatedActor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class SymfonyAdminAuthorization implements AdminAuthorization
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $security,
        private readonly TokenStorageInterface $tokens,
    ) {
    }

    public function requireUserManage(): UserId
    {
        return $this->require(AdminVoter::USERS);
    }

    public function requireRoleManage(): UserId
    {
        return $this->require(AdminVoter::ROLES);
    }

    private function require(string $attribute): UserId
    {
        $user = $this->tokens->getToken()?->getUser();

        if (!$user instanceof AuthenticatedActor || !$this->security->isGranted($attribute)) {
            throw AdminActionNotAllowed::create();
        }

        return $user->actorId();
    }

    public function requireAuditRead(): UserId
    {
        return $this->require(AdminVoter::AUDIT);
    }

}