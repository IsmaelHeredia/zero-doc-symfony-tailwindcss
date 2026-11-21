<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Application\Query\UserAccess;
use App\Identity\Application\Query\UserAccessReader;
use App\Shared\Domain\UserId;
use Doctrine\DBAL\Connection;

final class DbalUserAccessReader implements UserAccessReader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function forUser(UserId $userId): UserAccess
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT r.name AS role_name, p.code AS permission_code
               FROM user_role ur
               JOIN role r ON r.id = ur.role_id
          LEFT JOIN role_permission rp ON rp.role_id = r.id
          LEFT JOIN permission p ON p.id = rp.permission_id
              WHERE ur.user_id = ?',
            [$userId->value],
        );

        $roles = array_values(array_unique(array_column($rows, 'role_name')));
        $permissions = array_values(array_unique(array_filter(array_column($rows, 'permission_code'))));
        sort($roles);
        sort($permissions);

        return new UserAccess($roles, $permissions);
    }
}