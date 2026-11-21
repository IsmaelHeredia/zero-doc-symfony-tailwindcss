<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Persistence\Doctrine\Type;

use App\Paste\Domain\ValueObject\Ciphertext;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final class CiphertextType extends StringValueObjectType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    protected function valueObjectClass(): string
    {
        return Ciphertext::class;
    }

    protected function hydrate(string $value): object
    {
        return Ciphertext::fromBase64Url($value);
    }

    protected function extract(object $valueObject): string
    {
        \assert($valueObject instanceof Ciphertext);

        return $valueObject->value;
    }
}