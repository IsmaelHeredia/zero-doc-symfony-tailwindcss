<?php

declare(strict_types=1);

namespace App\Paste\Application\Audit;

use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\Event\PasteBurned;
use App\Paste\Domain\Event\PasteDeleted;
use App\Paste\Domain\Event\PasteDeletedByModerator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class RecordPasteDeletionAudit
{
    public function __construct(private DeletionLog $log)
    {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPasteDeleted(PasteDeleted $event): void
    {
        $this->log->record($event->pasteId, $event->reason, $event->actorId, $event->occurredAt);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPasteDeletedByModerator(PasteDeletedByModerator $event): void
    {
        $this->log->record($event->pasteId, DeletionReason::Moderator, $event->moderatorId, $event->occurredAt);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPasteBurned(PasteBurned $event): void
    {
        $this->log->record($event->pasteId, DeletionReason::Burned, null, $event->occurredAt);
    }
}