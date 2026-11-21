<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\Exception\CannotRemoveLastAdmin;
use App\Identity\Domain\LastAdminGuard;
use App\Identity\Domain\Role;
use App\Identity\Domain\User;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\RoleId;
use App\Identity\Domain\ValueObject\RoleName;
use App\Shared\Domain\UserId;
use App\Tests\Identity\Support\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

final class LastAdminGuardTest extends TestCase
{
    private InMemoryUserRepository $users;
    private LastAdminGuard $guard;
    private Role $admin;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->guard = new LastAdminGuard($this->users);
        $this->admin = Role::create(RoleId::generate(), RoleName::admin());
    }

    public function test_the_last_admin_cannot_lose_the_role(): void
    {
        $only = $this->userWith('a@example.com', $this->admin);

        $this->expectException(CannotRemoveLastAdmin::class);
        $this->guard->assertCanRevoke($only, $this->admin);
    }

    public function test_an_admin_can_lose_the_role_when_another_admin_remains(): void
    {
        $first = $this->userWith('a@example.com', $this->admin);
        $this->userWith('b@example.com', $this->admin);

        $this->guard->assertCanRevoke($first, $this->admin);

        $this->addToAssertionCount(1);
    }

    public function test_the_rule_only_applies_to_the_admin_role(): void
    {
        $member = Role::create(RoleId::generate(), RoleName::member());
        $only = $this->userWith('a@example.com', $member);

        $this->guard->assertCanRevoke($only, $member);

        $this->addToAssertionCount(1);
    }

    public function test_a_user_who_is_not_admin_is_never_blocked(): void
    {
        $this->userWith('a@example.com', $this->admin);
        $notAdmin = $this->userWith('b@example.com', Role::create(RoleId::generate(), RoleName::member()));

        $this->guard->assertCanRevoke($notAdmin, $this->admin);

        $this->addToAssertionCount(1);
    }

    private function userWith(string $email, Role $role): User
    {
        $now = new \DateTimeImmutable('2026-10-13 12:00:00');
        $user = User::register(UserId::generate(), Email::fromString($email), HashedPassword::fromHash('x'), $now);
        $user->assignRole($role->id(), $now);
        $this->users->save($user);

        return $user;
    }
}