<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Exception\EmailAlreadyRegistered;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\RoleId;
use App\Shared\Domain\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\AggregateHydrator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserRepository implements UserRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(User $user): void
    {
        try {
            $this->em->persist($user);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw EmailAlreadyRegistered::create();
        }

        $this->syncRoles($user);
    }

    public function findById(UserId $id): ?User
    {
        return $this->withRoles($this->em->find(User::class, $id));
    }

    public function findByEmail(Email $email): ?User
    {
        return $this->withRoles($this->em->getRepository(User::class)->findOneBy(['email' => $email]));
    }

    public function countWithRole(RoleId $roleId): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM user_role WHERE role_id = ?', [$roleId->value]);
    }

    private function withRoles(?User $user): ?User
    {
        if (null === $user) {
            return null;
        }

        $ids = $this->connection()->fetchFirstColumn('SELECT role_id FROM user_role WHERE user_id = ?', [$user->id()->value]);
        AggregateHydrator::set(User::class, $user, 'roleIds', array_map(RoleId::fromString(...), $ids));

        return $user;
    }

    private function syncRoles(User $user): void
    {
        $conn = $this->connection();
        $userId = $user->id()->value;

        $desired = array_map(static fn (RoleId $r): string => $r->value, $user->roleIds());
        $current = $conn->fetchFirstColumn('SELECT role_id FROM user_role WHERE user_id = ?', [$userId]);

        foreach (array_diff($desired, $current) as $roleId) {
            $conn->insert('user_role', ['user_id' => $userId, 'role_id' => $roleId]);
        }
        foreach (array_diff($current, $desired) as $roleId) {
            $conn->delete('user_role', ['user_id' => $userId, 'role_id' => $roleId]);
        }
    }

    private function connection(): Connection
    {
        return $this->em->getConnection();
    }
}