<?php

declare(strict_types=1);

namespace App\Organization\Application\Query;

use App\Organization\Application\Port\TagAuthorization;
use App\Organization\Domain\Exception\TagNotFound;
use App\Organization\Domain\ValueObject\TagId;
use Psr\Clock\ClockInterface;

final readonly class ListTaggedPastes
{
    public function __construct(
        private TagAuthorization $authorization,
        private TagReader $reader,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(string $tagId): array
    {
        $owner = $this->authorization->requireListOwnPastes();
        $id = TagId::fromString($tagId);

        return $this->reader->pastesWithTag($owner, $id, $this->clock->now()) ?? throw TagNotFound::withId($id);
    }
}