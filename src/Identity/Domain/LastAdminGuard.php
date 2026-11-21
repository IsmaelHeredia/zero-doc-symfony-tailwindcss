<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\Exception\CannotRemoveLastAdmin;

final readonly class LastAdminGuard
{
    public function __construct(private UserRepository $users)
    {
    }

    public function assertCanRevoke(User $user, Role $role): void
    {
        if (!$role->name()->isAdmin() || !$user->hasRole($role->id())) {
            return;
        }

        if ($this->users->countWithRole($role->id()) <= 1) {
            throw CannotRemoveLastAdmin::create();
        }
    }
}