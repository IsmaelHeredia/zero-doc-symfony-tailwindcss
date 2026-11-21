<?php

declare(strict_types=1);

namespace App\Identity\Application\Query;

final readonly class UserAccess
{
    public function __construct(public array $roles, public array $permissions)
    {
    }
}