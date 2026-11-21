<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\UserId;

final readonly class UserCreated implements DomainEvent
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $occurredAt)
    {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}