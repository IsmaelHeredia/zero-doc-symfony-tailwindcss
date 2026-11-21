<?php

declare(strict_types=1);

namespace App\Identity\Application\RevokeRole;

final readonly class RevokeRoleCommand
{
    public function __construct(public string $email, public string $roleName)
    {
    }
}