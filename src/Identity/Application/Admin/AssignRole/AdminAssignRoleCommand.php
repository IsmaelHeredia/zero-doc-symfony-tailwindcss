<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\AssignRole;

final readonly class AdminAssignRoleCommand
{
    public function __construct(public string $userId, public string $roleName)
    {
    }
}