<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\Event\PermissionGranted;
use App\Identity\Domain\Event\PermissionRevoked;
use App\Identity\Domain\ValueObject\PermissionCode;
use App\Identity\Domain\ValueObject\RoleId;
use App\Identity\Domain\ValueObject\RoleName;
use App\Shared\Domain\AggregateRoot;
use App\Identity\Domain\Exception\CannotRevokeProtectedPermission;

class Role extends AggregateRoot
{
    private array $permissions = [];

    public const ADMIN_PROTECTED = [PermissionCode::USER_MANAGE, PermissionCode::ROLE_MANAGE];

    private function __construct(private RoleId $id, private RoleName $name)
    {
    }

    public static function create(RoleId $id, RoleName $name): self
    {
        return new self($id, $name);
    }

    public function grant(PermissionCode $permission, \DateTimeImmutable $now): void
    {
        if ($this->has($permission)) {
            return;
        }

        $this->permissions[] = $permission;
        $this->record(new PermissionGranted($this->id, $permission, $now));
    }

    public function revoke(PermissionCode $permission, \DateTimeImmutable $now): void
    {
        if ($this->name->isAdmin() && \in_array($permission->value, self::ADMIN_PROTECTED, true)) {
            throw CannotRevokeProtectedPermission::create();
        }
        if (!$this->has($permission)) {
            return;
        }

        $this->permissions = array_values(array_filter(
            $this->permissions,
            static fn (PermissionCode $p): bool => !$p->equals($permission),
        ));
        $this->record(new PermissionRevoked($this->id, $permission, $now));
    }

    public function has(PermissionCode $permission): bool
    {
        foreach ($this->permissions as $p) {
            if ($p->equals($permission)) {
                return true;
            }
        }

        return false;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    public function id(): RoleId
    {
        return $this->id;
    }

    public function name(): RoleName
    {
        return $this->name;
    }
}