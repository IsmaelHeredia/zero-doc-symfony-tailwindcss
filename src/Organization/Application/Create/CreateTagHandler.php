<?php

declare(strict_types=1);

namespace App\Organization\Application\Create;

use App\Organization\Application\Exception\TagLimitReached;
use App\Organization\Application\Port\TagAuthorization;
use App\Organization\Domain\Exception\TagAlreadyExists;
use App\Organization\Domain\Tag;
use App\Organization\Domain\TagRepository;
use App\Organization\Domain\ValueObject\TagId;
use App\Organization\Domain\ValueObject\TagName;
use App\Shared\Application\EventBus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateTagHandler
{
    public function __construct(
        private TagRepository $tags,
        private TagAuthorization $authorization,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateTagCommand $command): CreatedTag
    {
        $owner = $this->authorization->requireManage();
        $name = TagName::fromString($command->name);

        if ($this->tags->countByOwner($owner) >= Tag::MAX_PER_OWNER) {
            throw TagLimitReached::create();
        }
        if (null !== $this->tags->findByOwnerAndName($owner, $name)) {
            throw TagAlreadyExists::withName($name);
        }

        $tag = Tag::create(TagId::generate(), $owner, $name, $this->clock->now());

        $this->tags->save($tag);
        $this->events->publish(...$tag->pullDomainEvents());

        return new CreatedTag($tag->id()->value, $tag->name()->value);
    }
}