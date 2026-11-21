<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\Exception\EmailAlreadyRegistered;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\RoleId;
use App\Shared\Domain\UserId;

interface UserRepository
{
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function countWithRole(RoleId $roleId): int;
}