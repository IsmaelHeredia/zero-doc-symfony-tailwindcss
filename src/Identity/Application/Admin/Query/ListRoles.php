<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Query;

use App\Identity\Application\Admin\AdminAuthorization;
use App\Identity\Domain\Role;
use App\Identity\Domain\ValueObject\PermissionCode;

final readonly class ListRoles
{
    public function __construct(private AdminAuthorization $authorization, private RoleDirectory $directory)
    {
    }

    public function __invoke(): RoleAdminView
    {
        $this->authorization->requireRoleManage();

        return new RoleAdminView($this->directory->roles(), PermissionCode::catalog(), Role::ADMIN_PROTECTED);
    }
}