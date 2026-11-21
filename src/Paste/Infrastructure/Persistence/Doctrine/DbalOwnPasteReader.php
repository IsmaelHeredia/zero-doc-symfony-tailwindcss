<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Persistence\Doctrine;

use App\Paste\Application\Query\OwnPasteReader;
use App\Paste\Application\Query\OwnPasteSummary;
use App\Paste\Application\Query\OwnPastesPage;
use App\Shared\Domain\UserId;
use Doctrine\DBAL\Connection;

final class DbalOwnPasteReader implements OwnPasteReader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function pastesOf(UserId $owner, \DateTimeImmutable $now, int $limit): OwnPastesPage
    {
        $limit = max(1, $limit);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, created_at, expires_at, views, max_views, burn_after_read, (kdf_salt IS NOT NULL) AS has_password
               FROM paste
              WHERE owner_id = ? AND (expires_at IS NULL OR expires_at > ?)
           ORDER BY created_at DESC, id
              LIMIT '.($limit + 1),
            [$owner->value, $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')],
        );

        $truncated = count($rows) > $limit;

        return new OwnPastesPage(
            array_map(fn (array $r): OwnPasteSummary => new OwnPasteSummary(
                (string) $r['id'],
                $this->iso((string) $r['created_at']),
                null === $r['expires_at'] ? null : $this->iso((string) $r['expires_at']),
                (int) $r['views'],
                null === $r['max_views'] ? null : (int) $r['max_views'],
                (bool) (int) $r['burn_after_read'],
                (bool) (int) $r['has_password'],
            ), array_slice($rows, 0, $limit)),
            $truncated,
        );
    }

    private function iso(string $utcDateTime): string
    {
        return (new \DateTimeImmutable($utcDateTime, new \DateTimeZone('UTC')))->format(\DATE_ATOM);
    }
}