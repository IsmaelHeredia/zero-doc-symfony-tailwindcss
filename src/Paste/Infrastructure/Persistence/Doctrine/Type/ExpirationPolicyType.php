<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Persistence\Doctrine\Type;

use App\Paste\Domain\ValueObject\ExpirationPolicy;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

final class ExpirationPolicyType extends Type
{
    private const FORMAT = 'Y-m-d H:i:s';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTimeTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof ExpirationPolicy) {
            throw InvalidType::new($value, self::class, [ExpirationPolicy::class]);
        }

        return $value->expiresAt?->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ExpirationPolicy
    {
        if (null === $value) {
            return ExpirationPolicy::fromStoredExpiry(null);
        }
        $date = \DateTimeImmutable::createFromFormat(self::FORMAT, (string) $value, new \DateTimeZone('UTC'));
        if (false === $date) {
            throw ValueNotConvertible::new($value, self::class);
        }

        return ExpirationPolicy::fromStoredExpiry($date);
    }
}