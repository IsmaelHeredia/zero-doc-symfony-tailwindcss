<?php

declare(strict_types=1);

namespace App\Organization\Application\Exception;

use App\Organization\Domain\Tag;

final class TagLimitReached extends \DomainException
{
    public static function create(): self
    {
        return new self(sprintf('Has alcanzado el máximo de %d tags.', Tag::MAX_PER_OWNER));
    }
}