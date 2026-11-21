<?php

declare(strict_types=1);

namespace App\Moderation\Application\Query;

use App\Moderation\Application\Port\ModerationAuthorization;

final readonly class ListOpenReports
{
    private const LIMIT = 100;

    public function __construct(private ModerationAuthorization $authorization, private ReportReader $reader)
    {
    }

    public function __invoke(): array
    {
        $this->authorization->requireReview();

        return $this->reader->openReports(self::LIMIT);
    }
}