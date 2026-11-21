<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Application\Admin\Query\RoleDirectory;
use App\Identity\Application\Admin\Query\RoleSummary;
use Doctrine\DBAL\Connection;

final class DbalRoleDirectory implements RoleDirectory
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function roles(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT r.name AS role_name, p.code AS permission_code
               FROM role r
          LEFT JOIN role_permission rp ON rp.role_id = r.id
          LEFT JOIN permission p ON p.id = rp.permission_id
           ORDER BY r.name, p.code',
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['role_name']] ??= [];
            if (null !== $row['permission_code']) {
                $grouped[(string) $row['role_name']][] = (string) $row['permission_code'];
            }
        }

        return array_values(array_map(
            static fn (string $name, array $permissions): RoleSummary => new RoleSummary($name, $permissions),
            array_keys($grouped),
            $grouped,
        ));
    }
}