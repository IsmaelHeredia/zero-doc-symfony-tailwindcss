<?php

declare(strict_types=1);

namespace App\Moderation\Infrastructure\Persistence\Doctrine;

use App\Moderation\Application\Query\ReportReader;
use App\Moderation\Application\Query\ReportSummary;
use App\Moderation\Domain\ValueObject\ReportStatus;
use Doctrine\DBAL\Connection;

final class DbalReportReader implements ReportReader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function openReports(int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT r.id, r.paste_id, r.reason, r.comment_text, r.created_at,
                    (SELECT COUNT(*) FROM abuse_report o WHERE o.paste_id = r.paste_id AND o.status = ?) AS open_for_paste
               FROM abuse_report r
              WHERE r.status = ?
           ORDER BY r.created_at, r.id
              LIMIT '.max(1, $limit),
            [ReportStatus::Open->value, ReportStatus::Open->value],
        );

        return array_map(static fn (array $r): ReportSummary => new ReportSummary(
            (string) $r['id'],
            (string) $r['paste_id'],
            (string) $r['reason'],
            null === $r['comment_text'] ? null : (string) $r['comment_text'],
            (new \DateTimeImmutable((string) $r['created_at'], new \DateTimeZone('UTC')))->format(\DATE_ATOM),
            (int) $r['open_for_paste'],
        ), $rows);
    }
}