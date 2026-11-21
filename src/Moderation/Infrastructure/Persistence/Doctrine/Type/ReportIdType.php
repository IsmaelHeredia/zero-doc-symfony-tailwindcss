<?php

declare(strict_types=1);

namespace App\Moderation\Infrastructure\Persistence\Doctrine\Type;

use App\Moderation\Domain\ValueObject\ReportId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class ReportIdType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return ReportId::class;
    }

    protected function hydrate(string $value): object
    {
        return ReportId::fromString($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof ReportId);

        return $valueObject->value;
    }
}