<?php

declare(strict_types=1);

namespace App\Moderation\Domain\Event;

use App\Moderation\Domain\ValueObject\ReportId;
use App\Shared\Domain\DomainEvent;

final readonly class ReportResolved implements DomainEvent
{
    public function __construct(public ReportId $reportId, public \DateTimeImmutable $occurredAt)
    {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}