<?php

declare(strict_types=1);

namespace App\Moderation\Domain\Event;

use App\Moderation\Domain\ValueObject\ReportId;
use App\Moderation\Domain\ValueObject\ReportReason;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\DomainEvent;

final readonly class AbuseReported implements DomainEvent
{
    public function __construct(
        public ReportId $reportId,
        public PasteId $pasteId,
        public ReportReason $reason,
        public \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}