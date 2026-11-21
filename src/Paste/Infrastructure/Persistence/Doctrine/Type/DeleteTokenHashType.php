<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Persistence\Doctrine\Type;

use App\Paste\Domain\ValueObject\DeleteTokenHash;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class DeleteTokenHashType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return DeleteTokenHash::class;
    }

    protected function hydrate(string $value): object
    {
        return DeleteTokenHash::fromString($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof DeleteTokenHash);

        return $valueObject->value;
    }
}