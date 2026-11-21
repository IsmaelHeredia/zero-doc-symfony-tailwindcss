<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidUserData;

final readonly class HashedPassword
{
    private function __construct(public string $value)
    {
    }

    public static function fromHash(string $hash): self
    {
        if ('' === $hash || strlen($hash) > 255) {
            throw InvalidUserData::because('Hash de contraseña inválido.');
        }

        return new self($hash);
    }
}