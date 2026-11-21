<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\RevokeRole;

final readonly class AdminRevokeRoleCommand
{
    public function __construct(public string $userId, public string $roleName)
    {
    }
}