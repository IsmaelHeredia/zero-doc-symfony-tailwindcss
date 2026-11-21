<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\Event\PermissionGranted;
use App\Identity\Domain\Event\PermissionRevoked;
use App\Identity\Domain\Exception\InvalidRoleData;
use App\Identity\Domain\Role;
use App\Identity\Domain\ValueObject\PermissionCode;
use App\Identity\Domain\ValueObject\RoleId;
use App\Identity\Domain\ValueObject\RoleName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function test_granting_is_idempotent_and_records_one_event(): void
    {
        $role = Role::create(RoleId::generate(), RoleName::fromString('moderator'));
        $code = PermissionCode::fromString(PermissionCode::PASTE_DELETE_ANY);

        $role->grant($code, $this->now());
        $role->grant($code, $this->now());

        self::assertTrue($role->has($code));
        self::assertCount(1, $role->permissions());
        $events = $role->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PermissionGranted::class, $events[0]);
    }

    public function test_revoking_removes_the_permission_and_records_event(): void
    {
        $role = Role::create(RoleId::generate(), RoleName::fromString('moderator'));
        $code = PermissionCode::fromString(PermissionCode::PASTE_DELETE_ANY);
        $role->grant($code, $this->now());
        $role->pullDomainEvents();

        $role->revoke($code, $this->now());
        $role->revoke($code, $this->now());

        self::assertFalse($role->has($code));
        $events = $role->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PermissionRevoked::class, $events[0]);
    }

    #[DataProvider('invalidNames')]
    public function test_a_role_cannot_be_nameless_or_malformed(string $name): void
    {
        $this->expectException(InvalidRoleData::class);
        RoleName::fromString($name);
    }

    public static function invalidNames(): iterable
    {
        yield 'vacío' => [''];
        yield 'solo espacios' => ['   '];
        yield 'empieza por número' => ['1admin'];
        yield 'caracteres raros' => ['ad min!'];
        yield 'demasiado largo' => [str_repeat('a', 33)];
    }

    public function test_role_name_is_normalized(): void
    {
        self::assertSame('admin', RoleName::fromString('  ADMIN ')->value);
        self::assertTrue(RoleName::fromString('Admin')->isAdmin());
    }

    #[DataProvider('invalidPermissionCodes')]
    public function test_permission_codes_must_follow_the_format(string $code): void
    {
        $this->expectException(InvalidRoleData::class);
        PermissionCode::fromString($code);
    }

    public static function invalidPermissionCodes(): iterable
    {
        yield 'vacío' => [''];
        yield 'sin punto' => ['pastecreate'];
        yield 'con espacios' => ['paste. create'];
        yield 'mayúsculas inválidas por dígitos' => ['paste.create1'];
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-13 12:00:00');
    }

    public function test_the_admin_role_can_never_lose_user_manage_or_role_manage(): void
    {
        $admin = Role::create(RoleId::generate(), RoleName::admin());
        foreach (Role::ADMIN_PROTECTED as $code) {
            $admin->grant(PermissionCode::fromString($code), $this->now());
        }
        $admin->pullDomainEvents();

        foreach (Role::ADMIN_PROTECTED as $code) {
            try {
                $admin->revoke(PermissionCode::fromString($code), $this->now());
                self::fail("Debió proteger $code.");
            } catch (\App\Identity\Domain\Exception\CannotRevokeProtectedPermission) {
            }
            self::assertTrue($admin->has(PermissionCode::fromString($code)));
        }
        self::assertSame([], $admin->pullDomainEvents());
    }

    public function test_the_protection_is_specific_to_admin_and_to_those_two_permissions(): void
    {
        $now = $this->now();
        $delete = PermissionCode::fromString(PermissionCode::PASTE_DELETE_ANY);
        $manage = PermissionCode::fromString(PermissionCode::USER_MANAGE);

        $admin = Role::create(RoleId::generate(), RoleName::admin());
        $admin->grant($delete, $now);
        $admin->revoke($delete, $now);
        self::assertFalse($admin->has($delete));

        $moderator = Role::create(RoleId::generate(), RoleName::fromString('moderator'));
        $moderator->grant($manage, $now);
        $moderator->revoke($manage, $now);
        self::assertFalse($moderator->has($manage));
    }
}