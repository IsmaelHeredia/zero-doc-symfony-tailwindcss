<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

final class AdminPermissions
{
    public const USER_MANAGE = 'user.manage';
    public const ROLE_MANAGE = 'role.manage';
    public const AUDIT_READ = 'audit.read';
}