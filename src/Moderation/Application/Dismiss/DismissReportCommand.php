<?php

declare(strict_types=1);

namespace App\Moderation\Application\Dismiss;

final readonly class DismissReportCommand
{
    public function __construct(public string $reportId)
    {
    }
}