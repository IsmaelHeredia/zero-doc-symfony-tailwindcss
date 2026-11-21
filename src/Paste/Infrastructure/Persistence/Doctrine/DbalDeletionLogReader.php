<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Persistence\Doctrine;

use App\Paste\Application\Query\DeletionLogEntry;
use App\Paste\Application\Query\DeletionLogReader;
use Doctrine\DBAL\Connection;

final class DbalDeletionLogReader implements DeletionLogReader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function latest(int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT deleted_at, paste_id, reason, deleted_by_user_id FROM paste_deletion_log ORDER BY id DESC LIMIT '.max(1, $limit),
        );

        return array_map(static fn (array $r): DeletionLogEntry => new DeletionLogEntry(
            (new \DateTimeImmutable((string) $r['deleted_at'], new \DateTimeZone('UTC')))->format(\DATE_ATOM),
            (string) $r['paste_id'],
            (string) $r['reason'],
            null === $r['deleted_by_user_id'] ? null : (string) $r['deleted_by_user_id'],
        ), $rows);
    }
}