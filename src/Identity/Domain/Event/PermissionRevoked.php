<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Identity\Domain\ValueObject\PermissionCode;
use App\Identity\Domain\ValueObject\RoleId;
use App\Shared\Domain\DomainEvent;

final readonly class PermissionRevoked implements DomainEvent
{
    public function __construct(public RoleId $roleId, public PermissionCode $permission, public \DateTimeImmutable $occurredAt)
    {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}