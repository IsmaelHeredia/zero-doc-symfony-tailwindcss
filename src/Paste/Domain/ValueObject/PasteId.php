<?php

declare(strict_types=1);

namespace App\Paste\Domain\ValueObject;

use App\Paste\Domain\Exception\InvalidPasteData;
use App\Shared\Domain\Base64Url;

final readonly class PasteId implements \Stringable
{
    private function __construct(public string $value)
    {
    }

    public static function generate(): self
    {
        return new self(Base64Url::encode(random_bytes(16)));
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{22}$/', $value)) {
            throw InvalidPasteData::because('PasteId inválido.');
        }

        return new self($value);
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