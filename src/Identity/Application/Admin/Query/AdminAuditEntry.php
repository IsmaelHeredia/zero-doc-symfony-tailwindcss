<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Query;

final readonly class AdminAuditEntry
{
    public function __construct(
        public string $occurredAt,
        public string $action,
        public string $actorId,
        public ?string $targetUserId,
        public string $roleName,
        public ?string $permissionCode,
    ) {
    }
}