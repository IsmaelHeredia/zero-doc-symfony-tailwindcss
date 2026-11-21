<?php

declare(strict_types=1);

namespace App\Tests\Identity\Application;

use App\Identity\Application\Admin\Audit\AdminAction;
use App\Identity\Application\Admin\AssignRole\AdminAssignRoleCommand;
use App\Identity\Application\Admin\AssignRole\AdminAssignRoleHandler;
use App\Identity\Application\Admin\Exception\AdminActionNotAllowed;
use App\Identity\Application\Admin\GrantPermission\GrantPermissionCommand;
use App\Identity\Application\Admin\GrantPermission\GrantPermissionHandler;
use App\Identity\Application\Admin\RevokePermission\RevokePermissionCommand;
use App\Identity\Application\Admin\RevokePermission\RevokePermissionHandler;
use App\Identity\Application\Admin\RevokeRole\AdminRevokeRoleCommand;
use App\Identity\Application\Admin\RevokeRole\AdminRevokeRoleHandler;
use App\Identity\Application\Register\RegisterUserCommand;
use App\Identity\Application\Register\RegisterUserHandler;
use App\Identity\Application\SeedRoles\SeedDefaultRolesCommand;
use App\Identity\Application\SeedRoles\SeedDefaultRolesHandler;
use App\Identity\Domain\Exception\CannotRemoveLastAdmin;
use App\Identity\Domain\Exception\CannotRevokeProtectedPermission;
use App\Identity\Domain\Exception\InvalidRoleData;
use App\Identity\Domain\Exception\RoleNotFound;
use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\LastAdminGuard;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\PermissionCode;
use App\Identity\Domain\ValueObject\RoleName;
use App\Shared\Domain\UserId;
use App\Tests\Identity\Support\FakePasswordHasher;
use App\Tests\Identity\Support\InMemoryAdminAuditLog;
use App\Tests\Identity\Support\InMemoryRoleRepository;
use App\Tests\Identity\Support\InMemoryUserRepository;
use App\Tests\Identity\Support\RecordingEventBus;
use App\Tests\Identity\Support\StubAdminAuthorization;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AdminHandlersTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryRoleRepository $roles;
    private InMemoryAdminAuditLog $audit;
    private RecordingEventBus $events;
    private MockClock $clock;
    private UserId $actor;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->roles = new InMemoryRoleRepository();
        $this->audit = new InMemoryAdminAuditLog();
        $this->events = new RecordingEventBus();
        $this->clock = new MockClock('2026-10-13 12:00:00');
        $this->actor = UserId::generate();

        (new SeedDefaultRolesHandler($this->roles, new RecordingEventBus(), $this->clock))(new SeedDefaultRolesCommand());
    }

    public function test_assigning_a_role_is_audited_with_actor_and_target_and_is_idempotent(): void
    {
        $target = $this->register('ana@example.com');

        $this->assign(true)(new AdminAssignRoleCommand($target, 'moderator'));
        $this->assign(true)(new AdminAssignRoleCommand($target, 'moderator'));

        self::assertCount(1, $this->audit->entries);
        $entry = $this->audit->entries[0];
        self::assertSame(AdminAction::RoleAssigned, $entry['action']);
        self::assertTrue($entry['actor']->equals($this->actor));
        self::assertSame($target, $entry['target']->value);
        self::assertSame('moderator', $entry['role']);
        self::assertNull($entry['permission']);
    }

    public function test_user_admin_requires_user_manage_and_changes_nothing_without_it(): void
    {
        $target = $this->register('ana@example.com');

        foreach ([
            fn () => $this->assign(false)(new AdminAssignRoleCommand($target, 'moderator')),
            fn () => $this->revoke(false)(new AdminRevokeRoleCommand($target, 'member')),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('Debió denegar.');
            } catch (AdminActionNotAllowed) {
            }
        }

        self::assertSame([], $this->audit->entries);
        $member = $this->roles->getByName(RoleName::member());
        self::assertTrue($this->users->findByEmail(Email::fromString('ana@example.com'))->hasRole($member->id()));
    }

    public function test_unknown_user_or_role_fail_without_trace(): void
    {
        $target = $this->register('ana@example.com');

        try {
            $this->assign(true)(new AdminAssignRoleCommand(UserId::generate()->value, 'moderator'));
            self::fail();
        } catch (UserNotFound) {
        }
        try {
            $this->assign(true)(new AdminAssignRoleCommand($target, 'inexistente'));
            self::fail();
        } catch (RoleNotFound) {
        }

        self::assertSame([], $this->audit->entries);
    }

    public function test_the_last_admin_cannot_be_demoted_over_http_either(): void
    {
        $only = $this->register('a@example.com', [RoleName::ADMIN]);

        try {
            $this->revoke(true)(new AdminRevokeRoleCommand($only, RoleName::ADMIN));
            self::fail('Debió impedir quitar el último admin.');
        } catch (CannotRemoveLastAdmin) {
        }
        self::assertSame([], $this->audit->entries);

        $this->register('b@example.com', [RoleName::ADMIN]);
        $this->revoke(true)(new AdminRevokeRoleCommand($only, RoleName::ADMIN));

        self::assertSame(AdminAction::RoleRevoked, $this->audit->entries[0]['action']);
    }

    public function test_granting_a_catalog_permission_is_audited_and_idempotent(): void
    {
        $this->grant(true)(new GrantPermissionCommand('moderator', PermissionCode::TAG_MANAGE_OWN));
        $this->grant(true)(new GrantPermissionCommand('moderator', PermissionCode::TAG_MANAGE_OWN));

        self::assertTrue($this->roles->getByName(RoleName::fromString('moderator'))->has(PermissionCode::fromString(PermissionCode::TAG_MANAGE_OWN)));
        self::assertCount(1, $this->audit->entries);
        self::assertSame(AdminAction::PermissionGranted, $this->audit->entries[0]['action']);
        self::assertSame('tag.manage_own', $this->audit->entries[0]['permission']);
        self::assertNull($this->audit->entries[0]['target']);
    }

    public function test_permissions_outside_the_catalog_are_rejected_before_touching_anything(): void
    {
        foreach (['paste.read_any', 'paste.view_content', 'inventado.cosa'] as $code) {
            try {
                $this->grant(true)(new GrantPermissionCommand('moderator', $code));
                self::fail("$code no debería poder concederse.");
            } catch (InvalidRoleData) {
            }
        }

        self::assertSame([], $this->audit->entries);
        self::assertCount(2, $this->roles->getByName(RoleName::fromString('moderator'))->permissions());
    }

    public function test_role_admin_requires_role_manage_and_user_manage_is_not_enough(): void
    {
        foreach ([
            fn () => (new GrantPermissionHandler($this->roles, new StubAdminAuthorization($this->actor, canUsers: true, canRoles: false), $this->audit, $this->events, $this->clock))(new GrantPermissionCommand('moderator', PermissionCode::PASTE_LARGE)),
            fn () => (new RevokePermissionHandler($this->roles, new StubAdminAuthorization($this->actor, canUsers: true, canRoles: false), $this->audit, $this->events, $this->clock))(new RevokePermissionCommand('moderator', PermissionCode::PASTE_DELETE_ANY)),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('Debió denegar.');
            } catch (AdminActionNotAllowed) {
            }
        }

        self::assertSame([], $this->audit->entries);
    }

    public function test_the_admin_role_keeps_user_manage_and_role_manage(): void
    {
        foreach ([PermissionCode::USER_MANAGE, PermissionCode::ROLE_MANAGE] as $code) {
            try {
                $this->revokePermission(true)(new RevokePermissionCommand(RoleName::ADMIN, $code));
                self::fail("Debió proteger $code.");
            } catch (CannotRevokeProtectedPermission) {
            }
        }

        $admin = $this->roles->getByName(RoleName::admin());
        self::assertTrue($admin->has(PermissionCode::fromString(PermissionCode::USER_MANAGE)));
        self::assertSame([], $this->audit->entries);

        $this->revokePermission(true)(new RevokePermissionCommand(RoleName::ADMIN, PermissionCode::PASTE_DELETE_ANY));
        self::assertSame(AdminAction::PermissionRevoked, $this->audit->entries[0]['action']);
    }

    private function register(string $email, array $extraRoles = []): string
    {
        return (new RegisterUserHandler($this->users, $this->roles, new FakePasswordHasher(), new RecordingEventBus(), $this->clock))(
            new RegisterUserCommand($email, 'correct horse battery', $extraRoles),
        );
    }

    private function assign(bool $allowed): AdminAssignRoleHandler
    {
        return new AdminAssignRoleHandler($this->users, $this->roles, new StubAdminAuthorization($this->actor, canUsers: $allowed), $this->audit, $this->events, $this->clock);
    }

    private function revoke(bool $allowed): AdminRevokeRoleHandler
    {
        return new AdminRevokeRoleHandler($this->users, $this->roles, new LastAdminGuard($this->users), new StubAdminAuthorization($this->actor, canUsers: $allowed), $this->audit, $this->events, $this->clock);
    }

    private function grant(bool $allowed): GrantPermissionHandler
    {
        return new GrantPermissionHandler($this->roles, new StubAdminAuthorization($this->actor, canRoles: $allowed), $this->audit, $this->events, $this->clock);
    }

    private function revokePermission(bool $allowed): RevokePermissionHandler
    {
        return new RevokePermissionHandler($this->roles, new StubAdminAuthorization($this->actor, canRoles: $allowed), $this->audit, $this->events, $this->clock);
    }
}