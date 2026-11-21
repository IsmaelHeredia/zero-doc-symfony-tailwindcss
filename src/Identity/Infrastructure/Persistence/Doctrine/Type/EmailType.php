<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class EmailType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return Email::class;
    }

    protected function hydrate(string $value): object
    {
        return Email::fromString($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof Email);

        return $valueObject->value;
    }
}