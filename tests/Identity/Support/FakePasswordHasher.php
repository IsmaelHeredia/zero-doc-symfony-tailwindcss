<?php

declare(strict_types=1);

namespace App\Tests\Identity\Support;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\PlainPassword;

final class FakePasswordHasher implements PasswordHasher
{
    public function hash(PlainPassword $plain): HashedPassword
    {
        return HashedPassword::fromHash('hashed:'.$plain->value);
    }
}