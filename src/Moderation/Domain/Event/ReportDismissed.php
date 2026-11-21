<?php

declare(strict_types=1);

namespace App\Moderation\Domain\Event;

use App\Moderation\Domain\ValueObject\ReportId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\UserId;

final readonly class ReportDismissed implements DomainEvent
{
    public function __construct(public ReportId $reportId, public UserId $reviewerId, public \DateTimeImmutable $occurredAt)
    {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}