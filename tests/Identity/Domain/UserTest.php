<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\Event\UserCreated;
use App\Identity\Domain\User;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Shared\Domain\UserId;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_registering_a_user_records_user_created(): void
    {
        $id = UserId::generate();

        $user = User::register(
            $id,
            Email::fromString('ana@example.com'),
            HashedPassword::fromHash('$argon2id$fake'),
            new \DateTimeImmutable('2026-10-13 12:00:00'),
        );

        $events = $user->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserCreated::class, $events[0]);
        self::assertTrue($events[0]->userId->equals($id));
        self::assertSame([], $user->pullDomainEvents());
    }
}