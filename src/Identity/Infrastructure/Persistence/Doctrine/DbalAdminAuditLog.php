<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Application\Admin\Audit\AdminAction;
use App\Identity\Application\Admin\Audit\AdminAuditLog;
use App\Shared\Domain\UserId;
use Doctrine\DBAL\Connection;

final class DbalAdminAuditLog implements AdminAuditLog
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function record(
        AdminAction $action,
        UserId $actor,
        \DateTimeImmutable $at,
        string $roleName,
        ?UserId $targetUser = null,
        ?string $permissionCode = null,
    ): void {
        $this->connection->insert('admin_audit_log', [
            'actor_id' => $actor->value,
            'action' => $action->value,
            'target_user_id' => $targetUser?->value,
            'role_name' => $roleName,
            'permission_code' => $permissionCode,
            'occurred_at' => $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }
}