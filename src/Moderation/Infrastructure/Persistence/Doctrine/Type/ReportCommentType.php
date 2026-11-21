<?php

declare(strict_types=1);

namespace App\Moderation\Infrastructure\Persistence\Doctrine\Type;

use App\Moderation\Domain\ValueObject\ReportComment;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class ReportCommentType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return ReportComment::class;
    }

    protected function hydrate(string $value): object
    {
        return ReportComment::fromString($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof ReportComment);

        return $valueObject->value;
    }
}