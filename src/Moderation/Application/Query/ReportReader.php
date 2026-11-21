<?php

declare(strict_types=1);

namespace App\Moderation\Application\Query;

interface ReportReader
{
    public function openReports(int $limit): array;
}