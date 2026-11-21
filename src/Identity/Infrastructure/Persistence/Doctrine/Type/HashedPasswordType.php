<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\ValueObject\HashedPassword;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class HashedPasswordType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return HashedPassword::class;
    }

    protected function hydrate(string $value): object
    {
        return HashedPassword::fromHash($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof HashedPassword);

        return $valueObject->value;
    }
}