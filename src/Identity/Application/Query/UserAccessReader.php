<?php

declare(strict_types=1);

namespace App\Identity\Application\Query;

use App\Shared\Domain\UserId;

interface UserAccessReader
{
    public function forUser(UserId $userId): UserAccess;
}