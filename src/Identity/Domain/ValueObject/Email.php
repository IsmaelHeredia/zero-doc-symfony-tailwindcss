<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidUserData;

final readonly class Email implements \Stringable
{
    private const MAX_LENGTH = 254;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        if ('' === $normalized
            || strlen($normalized) > self::MAX_LENGTH
            || false === filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw InvalidUserData::because('Email inválido.');
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}