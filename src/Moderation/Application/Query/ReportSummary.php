<?php

declare(strict_types=1);

namespace App\Moderation\Application\Query;

final readonly class ReportSummary
{
    public function __construct(
        public string $id,
        public string $pasteId,
        public string $reason,
        public ?string $comment,
        public string $createdAt,
        public int $openReportsForPaste,
    ) {
    }
}