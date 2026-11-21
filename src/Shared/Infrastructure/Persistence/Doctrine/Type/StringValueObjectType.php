<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

abstract class StringValueObjectType extends Type
{
    abstract protected function valueObjectClass(): string;

    abstract protected function hydrate(string $value): object;

    abstract protected function extract(object $valueObject): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        $class = $this->valueObjectClass();
        if (!$value instanceof $class) {
            throw InvalidType::new($value, static::class, [$class]);
        }

        return $this->extract($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?object
    {
        return null === $value ? null : $this->hydrate((string) $value);
    }
}