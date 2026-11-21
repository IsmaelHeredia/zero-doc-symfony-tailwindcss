<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Query;

interface RoleDirectory
{
    public function roles(): array;
}