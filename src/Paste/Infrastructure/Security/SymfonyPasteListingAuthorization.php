<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Security;

use App\Paste\Application\Exception\ListingNotAllowed;
use App\Paste\Application\Port\PasteListingAuthorization;
use App\Shared\Domain\UserId;
use App\Shared\Infrastructure\Security\AuthenticatedActor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class SymfonyPasteListingAuthorization implements PasteListingAuthorization
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $security,
        private readonly TokenStorageInterface $tokens,
    ) {
    }

    public function requireListOwn(): UserId
    {
        return $this->require(PasteListVoter::LIST_OWN);
    }

    public function requireAuditRead(): UserId
    {
        return $this->require(PasteListVoter::AUDIT_READ);
    }

    private function require(string $attribute): UserId
    {
        $user = $this->tokens->getToken()?->getUser();

        if (!$user instanceof AuthenticatedActor || !$this->security->isGranted($attribute)) {
            throw ListingNotAllowed::create();
        }

        return $user->actorId();
    }
}