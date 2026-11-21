<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\ValueObject\RoleName;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class RoleNameType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return RoleName::class;
    }

    protected function hydrate(string $value): object
    {
        return RoleName::fromString($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof RoleName);

        return $valueObject->value;
    }
}