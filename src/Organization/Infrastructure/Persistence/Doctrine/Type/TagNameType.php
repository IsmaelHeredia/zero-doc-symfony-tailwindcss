<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine\Type;

use App\Organization\Domain\ValueObject\TagName;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class TagNameType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return TagName::class;
    }

    protected function hydrate(string $value): object
    {
        return TagName::fromString($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof TagName);

        return $valueObject->value;
    }
}