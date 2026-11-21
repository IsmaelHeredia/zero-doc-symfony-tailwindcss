<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Query;

use App\Identity\Application\Admin\AdminAuthorization;

final readonly class ListAdminAudit
{
    public const LIMIT = 200;

    public function __construct(private AdminAuthorization $authorization, private AdminAuditReader $reader)
    {
    }

    public function __invoke(): array
    {
        $this->authorization->requireAuditRead();

        return $this->reader->latest(self::LIMIT);
    }
}