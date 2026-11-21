<?php

declare(strict_types=1);

namespace App\Organization\Domain\Event;

use App\Organization\Domain\ValueObject\TagId;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\DomainEvent;

final readonly class PasteTagged implements DomainEvent
{
    public function __construct(public TagId $tagId, public PasteId $pasteId, public \DateTimeImmutable $occurredAt)
    {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}