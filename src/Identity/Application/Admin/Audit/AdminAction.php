<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Audit;

enum AdminAction: string
{
    case RoleAssigned = 'role_assigned';
    case RoleRevoked = 'role_revoked';
    case PermissionGranted = 'permission_granted';
    case PermissionRevoked = 'permission_revoked';
}