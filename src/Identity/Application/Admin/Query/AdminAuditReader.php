<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Query;

interface AdminAuditReader
{
    public function latest(int $limit): array;
}