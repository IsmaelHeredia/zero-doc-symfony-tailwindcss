<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Application\Admin\Query\UserDirectory;
use App\Identity\Application\Admin\Query\UserListing;
use App\Identity\Application\Admin\Query\UserSummary;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final class DbalUserDirectory implements UserDirectory
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function users(int $limit): UserListing
    {
        $limit = max(1, $limit);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, email, created_at FROM user ORDER BY created_at, id LIMIT '.($limit + 1),
        );
        $truncated = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);

        $rolesByUser = [];
        if ([] !== $rows) {
            $ids = array_map(static fn (array $r): string => (string) $r['id'], $rows);
            $pairs = $this->connection->fetchAllAssociative(
                'SELECT ur.user_id, r.name FROM user_role ur JOIN role r ON r.id = ur.role_id WHERE ur.user_id IN (?) ORDER BY r.name',
                [$ids],
                [ArrayParameterType::STRING],
            );
            foreach ($pairs as $pair) {
                $rolesByUser[(string) $pair['user_id']][] = (string) $pair['name'];
            }
        }

        return new UserListing(
            array_map(static fn (array $r): UserSummary => new UserSummary(
                (string) $r['id'],
                (string) $r['email'],
                $rolesByUser[(string) $r['id']] ?? [],
                (new \DateTimeImmutable((string) $r['created_at'], new \DateTimeZone('UTC')))->format(\DATE_ATOM),
            ), $rows),
            $truncated,
            array_map('strval', $this->connection->fetchFirstColumn('SELECT name FROM role ORDER BY name')),
        );
    }
}