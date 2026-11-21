<?php

declare(strict_types=1);

namespace App\Organization\Domain\Exception;

use App\Organization\Domain\ValueObject\TagName;

final class TagAlreadyExists extends \DomainException
{
    public static function withName(TagName $name): self
    {
        return new self(sprintf('Ya tienes un tag "%s".', $name->value));
    }
}