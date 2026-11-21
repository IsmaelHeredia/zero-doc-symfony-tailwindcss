<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Exception\RoleAlreadyExists;
use App\Identity\Domain\Exception\RoleNotFound;
use App\Identity\Domain\Role;
use App\Identity\Domain\RoleRepository;
use App\Identity\Domain\ValueObject\PermissionCode;
use App\Identity\Domain\ValueObject\RoleName;
use App\Shared\Infrastructure\Persistence\Doctrine\AggregateHydrator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineRoleRepository implements RoleRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Role $role): void
    {
        try {
            $this->em->persist($role);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw RoleAlreadyExists::withName($role->name());
        }

        $this->syncPermissions($role);
    }

    public function findByName(RoleName $name): ?Role
    {
        return $this->withPermissions($this->em->getRepository(Role::class)->findOneBy(['name' => $name]));
    }

    public function getByName(RoleName $name): Role
    {
        return $this->findByName($name) ?? throw RoleNotFound::withName($name);
    }

    public function getByNameForUpdate(RoleName $name): Role
    {
        $role = $this->em
            ->createQuery('SELECT r FROM '.Role::class.' r WHERE r.name = :name')
            ->setParameter('name', $name->value)
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        return $this->withPermissions($role) ?? throw RoleNotFound::withName($name);
    }

    private function withPermissions(?Role $role): ?Role
    {
        if (null === $role) {
            return null;
        }

        $codes = $this->connection()->fetchFirstColumn(
            'SELECT p.code FROM role_permission rp JOIN permission p ON p.id = rp.permission_id WHERE rp.role_id = ?',
            [$role->id()->value],
        );
        AggregateHydrator::set(Role::class, $role, 'permissions', array_map(PermissionCode::fromString(...), $codes));

        return $role;
    }

    private function syncPermissions(Role $role): void
    {
        $conn = $this->connection();
        $roleId = $role->id()->value;

        $desired = array_map(static fn (PermissionCode $p): string => $p->value, $role->permissions());
        $current = $conn->fetchFirstColumn(
            'SELECT p.code FROM role_permission rp JOIN permission p ON p.id = rp.permission_id WHERE rp.role_id = ?',
            [$roleId],
        );

        foreach (array_diff($desired, $current) as $code) {
            $conn->executeStatement('INSERT IGNORE INTO permission (code) VALUES (?)', [$code]);
            $conn->executeStatement(
                'INSERT INTO role_permission (role_id, permission_id) SELECT ?, id FROM permission WHERE code = ?',
                [$roleId, $code],
            );
        }
        foreach (array_diff($current, $desired) as $code) {
            $conn->executeStatement(
                'DELETE rp FROM role_permission rp JOIN permission p ON p.id = rp.permission_id WHERE rp.role_id = ? AND p.code = ?',
                [$roleId, $code],
            );
        }
    }

    private function connection(): Connection
    {
        return $this->em->getConnection();
    }
}