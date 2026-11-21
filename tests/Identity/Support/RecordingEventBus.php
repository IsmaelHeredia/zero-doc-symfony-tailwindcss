<?php

declare(strict_types=1);

namespace App\Tests\Identity\Support;

use App\Shared\Application\EventBus;
use App\Shared\Domain\DomainEvent;

final class RecordingEventBus implements EventBus
{
    public array $published = [];

    public function publish(DomainEvent ...$events): void
    {
        array_push($this->published, ...$events);
    }
}