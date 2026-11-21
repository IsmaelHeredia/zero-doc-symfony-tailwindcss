<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Audit;

use App\Shared\Domain\UserId;

interface AdminAuditLog
{
    public function record(
        AdminAction $action,
        UserId $actor,
        \DateTimeImmutable $at,
        string $roleName,
        ?UserId $targetUser = null,
        ?string $permissionCode = null,
    ): void;
}