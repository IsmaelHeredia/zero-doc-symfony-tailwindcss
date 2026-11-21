<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\PlainPassword;

interface PasswordHasher
{
    public function hash(PlainPassword $plain): HashedPassword;
}