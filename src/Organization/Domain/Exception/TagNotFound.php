<?php

declare(strict_types=1);

namespace App\Organization\Domain\Exception;

use App\Organization\Domain\ValueObject\TagId;

final class TagNotFound extends \DomainException
{
    public static function withId(TagId $id): self
    {
        return new self(sprintf('Tag %s no encontrado.', $id->value));
    }
}