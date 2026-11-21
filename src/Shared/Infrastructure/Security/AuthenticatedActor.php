<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\UserId;

interface AuthenticatedActor
{
    public function actorId(): UserId;

    public function hasPermission(string $code): bool;
}