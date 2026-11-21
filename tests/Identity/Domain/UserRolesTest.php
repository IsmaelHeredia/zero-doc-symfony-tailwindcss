<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\Event\RoleAssigned;
use App\Identity\Domain\Event\RoleRevoked;
use App\Identity\Domain\User;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\RoleId;
use App\Shared\Domain\UserId;
use PHPUnit\Framework\TestCase;

final class UserRolesTest extends TestCase
{
    public function test_assigning_a_role_is_idempotent_and_records_event(): void
    {
        $user = $this->user();
        $roleId = RoleId::generate();
        $user->pullDomainEvents();

        $user->assignRole($roleId, $this->now());
        $user->assignRole($roleId, $this->now());

        self::assertTrue($user->hasRole($roleId));
        self::assertCount(1, $user->roleIds());
        $events = $user->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(RoleAssigned::class, $events[0]);
    }

    public function test_revoking_a_role_records_event_only_if_held(): void
    {
        $user = $this->user();
        $roleId = RoleId::generate();
        $user->assignRole($roleId, $this->now());
        $user->pullDomainEvents();

        $user->revokeRole(RoleId::generate(), $this->now());
        self::assertSame([], $user->pullDomainEvents());

        $user->revokeRole($roleId, $this->now());
        self::assertFalse($user->hasRole($roleId));
        self::assertInstanceOf(RoleRevoked::class, $user->pullDomainEvents()[0]);
    }

    private function user(): User
    {
        return User::register(UserId::generate(), Email::fromString('ana@example.com'), HashedPassword::fromHash('x'), $this->now());
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-13 12:00:00');
    }
}