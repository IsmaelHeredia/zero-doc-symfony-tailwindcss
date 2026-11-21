<?php

declare(strict_types=1);

namespace App\Tests\Identity\Support;

use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Identity\Domain\ValueObject\Email;
use App\Shared\Domain\UserId;

final class InMemoryUserRepository implements UserRepository
{
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->id()->value] = $user;
    }

    public function findById(UserId $id): ?User
    {
        return $this->users[$id->value] ?? null;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email()->equals($email)) {
                return $user;
            }
        }

        return null;
    }

    public function countWithRole(\App\Identity\Domain\ValueObject\RoleId $roleId): int
    {
        return count(array_filter($this->users, static fn(User $u): bool => $u->hasRole($roleId)));
    }

}