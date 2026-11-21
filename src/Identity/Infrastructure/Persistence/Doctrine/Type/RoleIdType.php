<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\ValueObject\RoleId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class RoleIdType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return RoleId::class;
    }

    protected function hydrate(string $value): object
    {
        return RoleId::fromString($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof RoleId);

        return $valueObject->value;
    }
}