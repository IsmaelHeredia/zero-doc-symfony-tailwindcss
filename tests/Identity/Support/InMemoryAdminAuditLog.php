<?php

declare(strict_types=1);

namespace App\Tests\Identity\Support;

use App\Identity\Application\Admin\Audit\AdminAction;
use App\Identity\Application\Admin\Audit\AdminAuditLog;
use App\Shared\Domain\UserId;

final class InMemoryAdminAuditLog implements AdminAuditLog
{
    public array $entries = [];

    public function record(AdminAction $action, UserId $actor, \DateTimeImmutable $at, string $roleName, ?UserId $targetUser = null, ?string $permissionCode = null): void
    {
        $this->entries[] = ['action' => $action, 'actor' => $actor, 'role' => $roleName, 'target' => $targetUser, 'permission' => $permissionCode];
    }
}