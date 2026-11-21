<?php

declare(strict_types=1);

namespace App\Organization\Application\Delete;

use App\Organization\Application\Port\TagAuthorization;
use App\Organization\Domain\TagRepository;
use App\Organization\Domain\ValueObject\TagId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteTagHandler
{
    public function __construct(private TagRepository $tags, private TagAuthorization $authorization)
    {
    }

    public function __invoke(DeleteTagCommand $command): void
    {
        $owner = $this->authorization->requireManage();

        $this->tags->remove($this->tags->getOwnedForUpdate(TagId::fromString($command->tagId), $owner));
    }
}