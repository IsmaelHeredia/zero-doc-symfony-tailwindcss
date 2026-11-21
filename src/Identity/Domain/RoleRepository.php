<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\Exception\RoleAlreadyExists;
use App\Identity\Domain\Exception\RoleNotFound;
use App\Identity\Domain\ValueObject\RoleName;

interface RoleRepository
{
    public function save(Role $role): void;

    public function findByName(RoleName $name): ?Role;

    public function getByName(RoleName $name): Role;

    public function getByNameForUpdate(RoleName $name): Role;
}