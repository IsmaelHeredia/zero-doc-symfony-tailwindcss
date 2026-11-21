<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Persistence\Doctrine;

use App\Paste\Application\Purge\ExpiredPasteFinder;
use Doctrine\DBAL\Connection;

final class DbalExpiredPasteFinder implements ExpiredPasteFinder
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function findExpiredIds(\DateTimeImmutable $now, int $limit): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM paste WHERE expires_at IS NOT NULL AND expires_at <= ? ORDER BY expires_at LIMIT '.max(1, $limit),
            [$now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')],
        );

        return array_map('strval', $ids);
    }
}