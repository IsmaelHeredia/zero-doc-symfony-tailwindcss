<?php

declare(strict_types=1);

namespace App\Organization\Domain\Event;

use App\Organization\Domain\ValueObject\TagId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\UserId;

final readonly class TagCreated implements DomainEvent
{
    public function __construct(public TagId $tagId, public UserId $ownerId, public \DateTimeImmutable $occurredAt)
    {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}