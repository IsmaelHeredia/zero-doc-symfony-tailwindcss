<?php

declare(strict_types=1);

namespace App\Paste\Domain\Event;

use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\UserId;

final readonly class PasteCreated implements DomainEvent
{
    public function __construct(public PasteId $pasteId, public ?UserId $ownerId, public \DateTimeImmutable $occurredAt)
    {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}