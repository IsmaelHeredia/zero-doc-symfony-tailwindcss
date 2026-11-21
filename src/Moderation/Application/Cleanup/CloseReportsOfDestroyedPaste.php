<?php

declare(strict_types=1);

namespace App\Moderation\Application\Cleanup;

use App\Moderation\Domain\AbuseReportRepository;
use App\Paste\Domain\Event\PasteBurned;
use App\Paste\Domain\Event\PasteDeleted;
use App\Paste\Domain\Event\PasteDeletedByModerator;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Application\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CloseReportsOfDestroyedPaste
{
    public function __construct(private AbuseReportRepository $reports, private EventBus $events)
    {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPasteDeleted(PasteDeleted $event): void
    {
        $this->close($event->pasteId, $event->occurredAt);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPasteDeletedByModerator(PasteDeletedByModerator $event): void
    {
        $this->close($event->pasteId, $event->occurredAt);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPasteBurned(PasteBurned $event): void
    {
        $this->close($event->pasteId, $event->occurredAt);
    }

    private function close(PasteId $pasteId, \DateTimeImmutable $at): void
    {
        foreach ($this->reports->findOpenByPaste($pasteId) as $report) {
            $report->resolveBecausePasteDestroyed($at);
            $this->reports->save($report);
            $this->events->publish(...$report->pullDomainEvents());
        }
    }
}