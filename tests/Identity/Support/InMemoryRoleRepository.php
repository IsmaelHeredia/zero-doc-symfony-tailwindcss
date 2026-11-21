<?php

declare(strict_types=1);

namespace App\Tests\Identity\Support;

use App\Identity\Domain\Exception\RoleNotFound;
use App\Identity\Domain\Role;
use App\Identity\Domain\RoleRepository;
use App\Identity\Domain\ValueObject\RoleName;

final class InMemoryRoleRepository implements RoleRepository
{
    private array $roles = [];

    public function save(Role $role): void
    {
        $this->roles[$role->name()->value] = $role;
    }

    public function findByName(RoleName $name): ?Role
    {
        return $this->roles[$name->value] ?? null;
    }

    public function getByName(RoleName $name): Role
    {
        return $this->findByName($name) ?? throw RoleNotFound::withName($name);
    }

    public function getByNameForUpdate(RoleName $name): Role
    {
        return $this->getByName($name);
    }
}