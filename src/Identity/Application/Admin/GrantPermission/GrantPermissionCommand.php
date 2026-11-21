<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\GrantPermission;

final readonly class GrantPermissionCommand
{
    public function __construct(public string $roleName, public string $permission)
    {
    }
}