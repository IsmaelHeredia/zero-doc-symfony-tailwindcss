<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidUserData;

final readonly class PlainPassword
{
    private const MIN_LENGTH = 12;
    private const MAX_LENGTH = 128;

    private function __construct(#[\SensitiveParameter] public string $value)
    {
    }

    public static function fromString(#[\SensitiveParameter] string $value): self
    {
        $length = mb_strlen($value);
        if ($length < self::MIN_LENGTH) {
            throw InvalidUserData::because(sprintf('La contraseña debe tener al menos %d caracteres.', self::MIN_LENGTH));
        }
        if ($length > self::MAX_LENGTH) {
            throw InvalidUserData::because(sprintf('La contraseña no puede superar los %d caracteres.', self::MAX_LENGTH));
        }

        return new self($value);
    }

    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }
}