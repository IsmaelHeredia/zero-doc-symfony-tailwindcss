<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidRoleData;
use App\Shared\Domain\Uuid4;

final readonly class RoleId implements \Stringable
{
    private function __construct(public string $value)
    {
    }

    public static function generate(): self
    {
        return new self(Uuid4::generate());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid4::isValid($value)) {
            throw InvalidRoleData::because('RoleId inválido (se espera UUID v4).');
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