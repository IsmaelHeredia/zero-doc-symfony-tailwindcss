<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Application\Admin\Query\AdminAuditEntry;
use App\Identity\Application\Admin\Query\AdminAuditReader;
use Doctrine\DBAL\Connection;

final class DbalAdminAuditReader implements AdminAuditReader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function latest(int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT occurred_at, action, actor_id, target_user_id, role_name, permission_code
               FROM admin_audit_log ORDER BY id DESC LIMIT '.max(1, $limit),
        );

        return array_map(static fn (array $r): AdminAuditEntry => new AdminAuditEntry(
            (new \DateTimeImmutable((string) $r['occurred_at'], new \DateTimeZone('UTC')))->format(\DATE_ATOM),
            (string) $r['action'],
            (string) $r['actor_id'],
            null === $r['target_user_id'] ? null : (string) $r['target_user_id'],
            (string) $r['role_name'],
            null === $r['permission_code'] ? null : (string) $r['permission_code'],
        ), $rows);
    }
}