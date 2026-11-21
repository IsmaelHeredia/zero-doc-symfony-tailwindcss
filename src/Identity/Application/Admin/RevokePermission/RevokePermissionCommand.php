<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\RevokePermission;

final readonly class RevokePermissionCommand
{
    public function __construct(public string $roleName, public string $permission)
    {
    }
}