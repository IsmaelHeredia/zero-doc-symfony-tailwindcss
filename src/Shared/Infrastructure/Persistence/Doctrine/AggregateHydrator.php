<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

final class AggregateHydrator
{
    public static function set(string $declaringClass, object $aggregate, string $property, mixed $value): void
    {
        (new \ReflectionProperty($declaringClass, $property))->setValue($aggregate, $value);
    }
}