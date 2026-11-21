<?php

declare(strict_types=1);

namespace App\Tests\Paste\Support;

use App\Paste\Application\Port\AuthorizationChecker;
use App\Paste\Application\Port\CreationRights;
use App\Paste\Application\Port\DeletionGrant;
use App\Paste\Domain\Paste;
use App\Paste\Domain\ValueObject\DeleteToken;

final class StubAuthorizationChecker implements AuthorizationChecker
{
    public function __construct(private readonly ?CreationRights $rights = null, private readonly ?DeletionGrant $grant = null)
    {
    }

    public function creationRights(): CreationRights
    {
        return $this->rights ?? throw new \LogicException('Este test no configuró CreationRights.');
    }

    public function deletionGrantFor(Paste $paste, ?DeleteToken $token): ?DeletionGrant
    {
        return $this->grant;
    }
}