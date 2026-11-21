<?php

declare(strict_types=1);

namespace App\Paste\Domain\Event;

use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\DomainEvent;

final readonly class PasteViewed implements DomainEvent
{
    public function __construct(public PasteId $pasteId, public \DateTimeImmutable $occurredAt)
    {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}