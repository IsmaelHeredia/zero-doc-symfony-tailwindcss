<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\Exception\InvalidUserData;
use App\Identity\Domain\ValueObject\PlainPassword;
use PHPUnit\Framework\TestCase;

final class PlainPasswordTest extends TestCase
{
    public function test_it_accepts_a_long_enough_password(): void
    {
        self::assertSame('correct horse battery', PlainPassword::fromString('correct horse battery')->value);
    }

    public function test_it_rejects_short_passwords(): void
    {
        $this->expectException(InvalidUserData::class);
        PlainPassword::fromString('short');
    }

    public function test_it_rejects_excessively_long_passwords(): void
    {
        $this->expectException(InvalidUserData::class);
        PlainPassword::fromString(str_repeat('a', 129));
    }

    public function test_it_does_not_leak_through_debug_output(): void
    {
        $password = PlainPassword::fromString('correct horse battery');

        self::assertStringNotContainsString('correct horse battery', print_r($password, true));
    }
}