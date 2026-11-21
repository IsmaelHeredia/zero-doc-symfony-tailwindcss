<?php

declare(strict_types=1);

namespace App\Organization\Domain\ValueObject;

use App\Organization\Domain\Exception\InvalidTagData;

final readonly class TagName implements \Stringable
{
    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw InvalidTagData::because('Nombre de tag inválido.');
        }

        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));

        if ('' === $normalized) {
            throw InvalidTagData::because('Un tag no puede quedar sin nombre.');
        }
        if (1 !== preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _-]{0,31}$/u', $normalized)) {
            throw InvalidTagData::because('Nombre de tag inválido (1-32 caracteres: letras, números, espacio, _ y -).');
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