<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Persistence\Doctrine\Type;

use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class PasteIdType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return PasteId::class;
    }

    protected function hydrate(string $value): object
    {
        return PasteId::fromString($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof PasteId);

        return $valueObject->value;
    }
}