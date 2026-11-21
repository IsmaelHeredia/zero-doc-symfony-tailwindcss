<?php

declare(strict_types=1);

namespace App\Organization\Application\Cleanup;

use App\Organization\Domain\TagRepository;
use App\Paste\Domain\Event\PasteBurned;
use App\Paste\Domain\Event\PasteDeleted;
use App\Paste\Domain\Event\PasteDeletedByModerator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class RemoveDestroyedPasteFromTags
{
    public function __construct(private TagRepository $tags)
    {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPasteDeleted(PasteDeleted $event): void
    {
        $this->tags->forgetPaste($event->pasteId);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPasteDeletedByModerator(PasteDeletedByModerator $event): void
    {
        $this->tags->forgetPaste($event->pasteId);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPasteBurned(PasteBurned $event): void
    {
        $this->tags->forgetPaste($event->pasteId);
    }
}