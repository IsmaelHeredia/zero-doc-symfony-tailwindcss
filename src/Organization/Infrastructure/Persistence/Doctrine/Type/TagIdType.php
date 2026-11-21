<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine\Type;

use App\Organization\Domain\ValueObject\TagId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class TagIdType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return TagId::class;
    }

    protected function hydrate(string $value): object
    {
        return TagId::fromString($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof TagId);

        return $valueObject->value;
    }
}