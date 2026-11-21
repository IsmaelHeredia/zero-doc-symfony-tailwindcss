<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidRoleData;

final readonly class RoleName implements \Stringable
{
    public const ADMIN = 'admin';
    public const MODERATOR = 'moderator';
    public const MEMBER = 'member';

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        if ('' === $normalized) {
            throw InvalidRoleData::because('Un rol no puede quedar sin nombre.');
        }
        if (1 !== preg_match('/^[a-z][a-z0-9_]{1,31}$/', $normalized)) {
            throw InvalidRoleData::because('Nombre de rol inválido (2-32 caracteres: a-z, 0-9 y _).');
        }

        return new self($normalized);
    }

    public static function admin(): self
    {
        return new self(self::ADMIN);
    }

    public static function member(): self
    {
        return new self(self::MEMBER);
    }

    public function isAdmin(): bool
    {
        return self::ADMIN === $this->value;
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