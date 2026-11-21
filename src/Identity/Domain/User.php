<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\Event\RoleAssigned;
use App\Identity\Domain\Event\RoleRevoked;
use App\Identity\Domain\Event\UserCreated;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\RoleId;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\UserId;

class User extends AggregateRoot
{
    private array $roleIds = [];

    private function __construct(
        private UserId $id,
        private Email $email,
        private HashedPassword $passwordHash,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(UserId $id, Email $email, HashedPassword $passwordHash, \DateTimeImmutable $now): self
    {
        $user = new self($id, $email, $passwordHash, $now);
        $user->record(new UserCreated($id, $now));

        return $user;
    }

    public function assignRole(RoleId $roleId, \DateTimeImmutable $now): void
    {
        if ($this->hasRole($roleId)) {
            return;
        }

        $this->roleIds[] = $roleId;
        $this->record(new RoleAssigned($this->id, $roleId, $now));
    }

    public function revokeRole(RoleId $roleId, \DateTimeImmutable $now): void
    {
        if (!$this->hasRole($roleId)) {
            return;
        }

        $this->roleIds = array_values(array_filter(
            $this->roleIds,
            static fn (RoleId $r): bool => !$r->equals($roleId),
        ));
        $this->record(new RoleRevoked($this->id, $roleId, $now));
    }

    public function hasRole(RoleId $roleId): bool
    {
        foreach ($this->roleIds as $r) {
            if ($r->equals($roleId)) {
                return true;
            }
        }

        return false;
    }

    public function roleIds(): array
    {
        return $this->roleIds;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): HashedPassword
    {
        return $this->passwordHash;
    }
}