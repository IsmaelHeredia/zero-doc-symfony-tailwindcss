<?php

declare(strict_types=1);

namespace App\Organization\Application\Detach;

use App\Organization\Application\Port\TagAuthorization;
use App\Organization\Domain\TagRepository;
use App\Organization\Domain\ValueObject\TagId;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Application\EventBus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DetachPasteFromTagHandler
{
    public function __construct(
        private TagRepository $tags,
        private TagAuthorization $authorization,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DetachPasteFromTagCommand $command): void
    {
        $owner = $this->authorization->requireManage();
        $tag = $this->tags->getOwnedForUpdate(TagId::fromString($command->tagId), $owner);

        $tag->detach(PasteId::fromString($command->pasteId), $this->clock->now());

        $this->tags->save($tag);
        $this->events->publish(...$tag->pullDomainEvents());
    }
}