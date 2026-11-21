<?php

declare(strict_types=1);

namespace App\Identity\Application\SeedRoles;

use App\Identity\Domain\ValueObject\PermissionCode;
use App\Identity\Domain\ValueObject\RoleName;

final class DefaultRoles
{
    public static function definition(): array
    {
        return [
            RoleName::MEMBER => [
                PermissionCode::PASTE_CREATE,
                PermissionCode::PASTE_CREATE_PERMANENT,
                PermissionCode::PASTE_LARGE,
                PermissionCode::PASTE_LIST_OWN,
                PermissionCode::TAG_MANAGE_OWN,
            ],
            RoleName::MODERATOR => [
                PermissionCode::PASTE_DELETE_ANY,
                PermissionCode::REPORT_REVIEW,
            ],
            RoleName::ADMIN => [
                PermissionCode::PASTE_DELETE_ANY,
                PermissionCode::REPORT_REVIEW,
                PermissionCode::AUDIT_READ,
                PermissionCode::USER_MANAGE,
                PermissionCode::ROLE_MANAGE,
            ],
        ];
    }
}