<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Persistence\Doctrine\Type;

use App\Paste\Domain\ValueObject\InitializationVector;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class InitializationVectorType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return InitializationVector::class;
    }

    protected function hydrate(string $value): object
    {
        return InitializationVector::fromBase64Url($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof InitializationVector);

        return $valueObject->value;
    }
}