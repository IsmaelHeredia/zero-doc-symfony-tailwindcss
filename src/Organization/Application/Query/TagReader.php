<?php

declare(strict_types=1);

namespace App\Organization\Application\Query;

use App\Organization\Domain\ValueObject\TagId;
use App\Shared\Domain\UserId;

interface TagReader
{
    public function tagsOf(UserId $owner): array;
    public function pastesWithTag(UserId $owner, TagId $tag, \DateTimeImmutable $now): ?array;
}