<?php

declare(strict_types=1);

namespace App\Organization\Application\Attach;

use App\Organization\Application\Exception\PasteNotOwnedByActor;
use App\Organization\Application\Port\PasteOwnership;
use App\Organization\Application\Port\TagAuthorization;
use App\Organization\Domain\TagRepository;
use App\Organization\Domain\ValueObject\TagId;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Application\EventBus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AttachPasteToTagHandler
{
    public function __construct(
        private TagRepository $tags,
        private TagAuthorization $authorization,
        private PasteOwnership $pastes,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AttachPasteToTagCommand $command): void
    {
        $owner = $this->authorization->requireManage();

        $tag = $this->tags->getOwnedForUpdate(TagId::fromString($command->tagId), $owner);

        $pasteId = PasteId::fromString($command->pasteId);
        if (!$this->pastes->isOwnedBy($pasteId, $owner)) {
            throw PasteNotOwnedByActor::create();
        }

        $tag->attach($pasteId, $this->clock->now());

        $this->tags->save($tag);
        $this->events->publish(...$tag->pullDomainEvents());
    }
}