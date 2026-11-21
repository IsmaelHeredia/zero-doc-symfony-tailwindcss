<?php

declare(strict_types=1);

namespace App\Tests\Identity\Application;

use App\Identity\Application\AssignRole\AssignRoleCommand;
use App\Identity\Application\AssignRole\AssignRoleHandler;
use App\Identity\Application\Register\RegisterUserCommand;
use App\Identity\Application\Register\RegisterUserHandler;
use App\Identity\Application\RevokeRole\RevokeRoleCommand;
use App\Identity\Application\RevokeRole\RevokeRoleHandler;
use App\Identity\Application\SeedRoles\DefaultRoles;
use App\Identity\Application\SeedRoles\SeedDefaultRolesCommand;
use App\Identity\Application\SeedRoles\SeedDefaultRolesHandler;
use App\Identity\Domain\Exception\CannotRemoveLastAdmin;
use App\Identity\Domain\Exception\RoleNotFound;
use App\Identity\Domain\LastAdminGuard;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\PermissionCode;
use App\Identity\Domain\ValueObject\RoleName;
use App\Tests\Identity\Support\FakePasswordHasher;
use App\Tests\Identity\Support\InMemoryRoleRepository;
use App\Tests\Identity\Support\InMemoryUserRepository;
use App\Tests\Identity\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class RoleHandlersTest extends TestCase
{
    private const PASSWORD = 'correct horse battery';

    private InMemoryUserRepository $users;
    private InMemoryRoleRepository $roles;
    private RegisterUserHandler $register;
    private AssignRoleHandler $assign;
    private RevokeRoleHandler $revoke;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->roles = new InMemoryRoleRepository();
        $events = new RecordingEventBus();
        $clock = new MockClock('2026-10-13 12:00:00');

        (new SeedDefaultRolesHandler($this->roles, $events, $clock))(new SeedDefaultRolesCommand());

        $this->register = new RegisterUserHandler($this->users, $this->roles, new FakePasswordHasher(), $events, $clock);
        $this->assign = new AssignRoleHandler($this->users, $this->roles, $events, $clock);
        $this->revoke = new RevokeRoleHandler($this->users, $this->roles, new LastAdminGuard($this->users), $events, $clock);
    }

    public function test_seed_creates_default_roles_with_their_permissions_and_is_idempotent(): void
    {        
        $seed = new SeedDefaultRolesHandler($this->roles, new RecordingEventBus(), new MockClock('2026-10-13 12:00:00'));
        $seed(new SeedDefaultRolesCommand());

        $admin = $this->roles->getByName(RoleName::admin());
        self::assertTrue($admin->has(PermissionCode::fromString(PermissionCode::ROLE_MANAGE)));
        self::assertTrue($admin->has(PermissionCode::fromString(PermissionCode::PASTE_DELETE_ANY)));
        self::assertCount(5, $admin->permissions());

        $member = $this->roles->getByName(RoleName::member());
        self::assertFalse($member->has(PermissionCode::fromString(PermissionCode::PASTE_DELETE_ANY)));
    }

    public function test_the_permission_catalog_is_a_conscious_decision(): void
    {
        $all = array_values(array_unique(array_merge(...array_values(DefaultRoles::definition()))));
        sort($all);

        self::assertSame([
            'audit.read',
            'paste.create',
            'paste.create_permanent',
            'paste.delete_any',
            'paste.large',
            'paste.list_own',
            'report.review',
            'role.manage',
            'tag.manage_own',
            'user.manage',
        ], $all);
    }

    public function test_every_registered_user_is_a_member(): void
    {
        ($this->register)(new RegisterUserCommand('ana@example.com', self::PASSWORD));

        $user = $this->users->findByEmail(Email::fromString('ana@example.com'));
        $member = $this->roles->getByName(RoleName::member());
        $admin = $this->roles->getByName(RoleName::admin());
        self::assertTrue($user->hasRole($member->id()));
        self::assertFalse($user->hasRole($admin->id()));
    }

    public function test_extra_roles_are_assigned_on_top_of_member(): void
    {
        ($this->register)(new RegisterUserCommand('root@example.com', self::PASSWORD, [RoleName::ADMIN]));

        $user = $this->users->findByEmail(Email::fromString('root@example.com'));
        self::assertTrue($user->hasRole($this->roles->getByName(RoleName::admin())->id()));
        self::assertTrue($user->hasRole($this->roles->getByName(RoleName::member())->id()));
    }

    public function test_registering_without_seeded_roles_gives_a_clear_error(): void
    {
        $register = new RegisterUserHandler(
            $this->users,
            new InMemoryRoleRepository(),
            new FakePasswordHasher(),
            new RecordingEventBus(),
            new MockClock('2026-10-13 12:00:00'),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('app:identity:seed');
        $register(new RegisterUserCommand('ana@example.com', self::PASSWORD));
    }

    public function test_assigning_an_unknown_role_fails(): void
    {
        ($this->register)(new RegisterUserCommand('ana@example.com', self::PASSWORD));

        $this->expectException(RoleNotFound::class);
        ($this->assign)(new AssignRoleCommand('ana@example.com', 'inexistente'));
    }

    public function test_the_last_admin_cannot_be_demoted_but_one_of_two_can(): void
    {
        ($this->register)(new RegisterUserCommand('a@example.com', self::PASSWORD, [RoleName::ADMIN]));

        try {
            ($this->revoke)(new RevokeRoleCommand('a@example.com', RoleName::ADMIN));
            self::fail('Debió impedir quitar el último admin.');
        } catch (CannotRemoveLastAdmin) {
        }

        ($this->register)(new RegisterUserCommand('b@example.com', self::PASSWORD, [RoleName::ADMIN]));
        ($this->revoke)(new RevokeRoleCommand('a@example.com', RoleName::ADMIN));

        $a = $this->users->findByEmail(Email::fromString('a@example.com'));
        self::assertFalse($a->hasRole($this->roles->getByName(RoleName::admin())->id()));

        $this->expectException(CannotRemoveLastAdmin::class);
        ($this->revoke)(new RevokeRoleCommand('b@example.com', RoleName::ADMIN));
    }

    public function test_non_admin_roles_can_be_revoked_freely(): void
    {
        ($this->register)(new RegisterUserCommand('ana@example.com', self::PASSWORD));

        ($this->revoke)(new RevokeRoleCommand('ana@example.com', RoleName::MEMBER));

        $user = $this->users->findByEmail(Email::fromString('ana@example.com'));
        self::assertSame([], $user->roleIds());
    }
}