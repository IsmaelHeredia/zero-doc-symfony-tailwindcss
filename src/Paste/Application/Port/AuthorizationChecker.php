<?php

declare(strict_types=1);

namespace App\Paste\Application\Port;

use App\Paste\Domain\Paste;
use App\Paste\Domain\ValueObject\DeleteToken;

interface AuthorizationChecker
{
    public function creationRights(): CreationRights;

    public function deletionGrantFor(Paste $paste, ?DeleteToken $token): ?DeletionGrant;
}