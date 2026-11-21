<?php

declare(strict_types=1);

namespace App\Paste\Domain\Event;

use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\UserId;

final readonly class PasteDeleted implements DomainEvent
{
    public function __construct(
        public PasteId $pasteId,
        public DeletionReason $reason,
        public \DateTimeImmutable $occurredAt,
        public ?UserId $actorId = null,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}