<?php

declare(strict_types=1);

namespace App\Identity\Application\AssignRole;

final readonly class AssignRoleCommand
{
    public function __construct(public string $email, public string $roleName)
    {
    }
}