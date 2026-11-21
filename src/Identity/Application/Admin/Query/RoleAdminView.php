<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Query;

final readonly class RoleAdminView
{
    public function __construct(public array $roles, public array $catalog, public array $protectedAdminPermissions)
    {
    }
}