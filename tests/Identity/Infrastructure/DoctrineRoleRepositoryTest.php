<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure;

use App\Identity\Domain\Exception\RoleAlreadyExists;
use App\Identity\Domain\Role;
use App\Identity\Domain\ValueObject\PermissionCode;
use App\Identity\Domain\ValueObject\RoleId;
use App\Identity\Domain\ValueObject\RoleName;
use App\Identity\Infrastructure\Persistence\Doctrine\DoctrineRoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRoleRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DoctrineRoleRepository $roles;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->roles = new DoctrineRoleRepository($this->em);
    }

    public function test_permissions_roundtrip_through_the_pivot_table(): void
    {
        $name = RoleName::fromString('r'.bin2hex(random_bytes(4)));
        $now = new \DateTimeImmutable('2026-10-13 12:00:00');
        $a = PermissionCode::fromString(PermissionCode::PASTE_DELETE_ANY);
        $b = PermissionCode::fromString(PermissionCode::USER_MANAGE);

        $role = Role::create(RoleId::generate(), $name);
        $role->grant($a, $now);
        $role->grant($b, $now);
        $this->roles->save($role);
        $this->em->clear();

        $loaded = $this->roles->getByName($name);
        self::assertCount(2, $loaded->permissions());
        self::assertTrue($loaded->has($a));
        self::assertTrue($loaded->has($b));

        $loaded->revoke($a, $now);
        $this->roles->save($loaded);
        $this->em->clear();

        $reloaded = $this->roles->getByName($name);
        self::assertFalse($reloaded->has($a));
        self::assertTrue($reloaded->has($b));
    }

    public function test_duplicate_role_name_is_rejected(): void
    {
        $name = RoleName::fromString('r'.bin2hex(random_bytes(4)));
        $this->roles->save(Role::create(RoleId::generate(), $name));

        $this->expectException(RoleAlreadyExists::class);
        $this->roles->save(Role::create(RoleId::generate(), $name));
    }

    public function test_get_by_name_for_update_works_inside_a_transaction(): void
    {
        $name = RoleName::fromString('r'.bin2hex(random_bytes(4)));
        $this->roles->save(Role::create(RoleId::generate(), $name));
        $this->em->clear();

        $role = $this->em->wrapInTransaction(fn () => $this->roles->getByNameForUpdate($name));

        self::assertTrue($role->name()->equals($name));
    }
}