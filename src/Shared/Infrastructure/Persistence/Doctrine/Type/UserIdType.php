<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\UserId;

final class UserIdType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return UserId::class;
    }

    protected function hydrate(string $value): object
    {
        return UserId::fromString($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof UserId);

        return $valueObject->value;
    }
}