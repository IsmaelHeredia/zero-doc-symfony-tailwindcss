<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Application\Query\TaggedPaste;
use App\Organization\Application\Query\TagReader;
use App\Organization\Application\Query\TagSummary;
use App\Organization\Domain\ValueObject\TagId;
use App\Shared\Domain\UserId;
use Doctrine\DBAL\Connection;

final class DbalTagReader implements TagReader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function tagsOf(UserId $owner): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT t.id, t.name, COUNT(pt.paste_id) AS paste_count
               FROM tag t
          LEFT JOIN paste_tag pt ON pt.tag_id = t.id
              WHERE t.owner_id = ?
           GROUP BY t.id, t.name
           ORDER BY t.name',
            [$owner->value],
        );

        return array_map(
            static fn (array $r): TagSummary => new TagSummary((string) $r['id'], (string) $r['name'], (int) $r['paste_count']),
            $rows,
        );
    }

    public function pastesWithTag(UserId $owner, TagId $tag, \DateTimeImmutable $now): ?array
    {
        $exists = $this->connection->fetchOne('SELECT 1 FROM tag WHERE id = ? AND owner_id = ?', [$tag->value, $owner->value]);
        if (false === $exists) {
            return null;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT p.id, p.created_at, p.expires_at, p.views
               FROM paste_tag pt
               JOIN tag t   ON t.id = pt.tag_id
               JOIN paste p ON p.id = pt.paste_id AND p.owner_id = t.owner_id
              WHERE pt.tag_id = ? AND t.owner_id = ?
                AND (p.expires_at IS NULL OR p.expires_at > ?)
           ORDER BY p.created_at DESC',
            [$tag->value, $owner->value, $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')],
        );

        return array_map(fn (array $r): TaggedPaste => new TaggedPaste(
            (string) $r['id'],
            $this->iso((string) $r['created_at']),
            null === $r['expires_at'] ? null : $this->iso((string) $r['expires_at']),
            (int) $r['views'],
        ), $rows);
    }

    private function iso(string $utcDateTime): string
    {
        return (new \DateTimeImmutable($utcDateTime, new \DateTimeZone('UTC')))->format(\DATE_ATOM);
    }
}