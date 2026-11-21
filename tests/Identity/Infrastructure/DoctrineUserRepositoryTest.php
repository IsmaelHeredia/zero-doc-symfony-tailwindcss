<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure;

use App\Identity\Domain\Role;
use App\Identity\Domain\User;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\RoleId;
use App\Identity\Domain\ValueObject\RoleName;
use App\Identity\Infrastructure\Persistence\Doctrine\DoctrineRoleRepository;
use App\Identity\Infrastructure\Persistence\Doctrine\DoctrineUserRepository;
use App\Shared\Domain\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DoctrineUserRepository $users;
    private DoctrineRoleRepository $roles;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->users = new DoctrineUserRepository($this->em);
        $this->roles = new DoctrineRoleRepository($this->em);
    }

    public function test_roles_roundtrip_through_user_role_pivot(): void
    {
        $now = new \DateTimeImmutable('2026-10-13 12:00:00');
        $r1 = $this->newRole();
        $r2 = $this->newRole();

        $email = Email::fromString(sprintf('u-%s@example.test', bin2hex(random_bytes(4))));
        $user = User::register(UserId::generate(), $email, HashedPassword::fromHash('x'), $now);
        $user->assignRole($r1->id(), $now);
        $user->assignRole($r2->id(), $now);
        $this->users->save($user);
        $this->em->clear();

        $loaded = $this->users->findByEmail($email);
        self::assertTrue($loaded->hasRole($r1->id()));
        self::assertTrue($loaded->hasRole($r2->id()));
        self::assertSame(1, $this->users->countWithRole($r1->id()));

        $loaded->revokeRole($r2->id(), $now);
        $this->users->save($loaded);
        $this->em->clear();

        $reloaded = $this->users->findById($loaded->id());
        self::assertTrue($reloaded->hasRole($r1->id()));
        self::assertFalse($reloaded->hasRole($r2->id()));
        self::assertSame(0, $this->users->countWithRole($r2->id()));
    }

    private function newRole(): Role
    {
        $role = Role::create(RoleId::generate(), RoleName::fromString('r'.bin2hex(random_bytes(4))));
        $this->roles->save($role);

        return $role;
    }
}