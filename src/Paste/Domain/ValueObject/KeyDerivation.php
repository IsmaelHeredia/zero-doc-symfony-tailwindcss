<?php

declare(strict_types=1);

namespace App\Paste\Domain\ValueObject;

use App\Paste\Domain\Exception\InvalidPasteData;
use App\Shared\Domain\Base64Url;

final readonly class KeyDerivation
{
    public const MIN_ITERATIONS = 600_000;
    public const MAX_ITERATIONS = 2_000_000;
    private const SALT_BYTES = 16;

    private function __construct(public string $salt, public int $iterations)
    {
    }

    public static function fromNullable(?string $salt, ?int $iterations): ?self
    {
        if (null === $salt && null === $iterations) {
            return null;
        }
        if (null === $salt || null === $iterations) {
            throw InvalidPasteData::because('kdf requiere "salt" e "iterations".');
        }

        $bytes = Base64Url::decode($salt);
        if (null === $bytes || self::SALT_BYTES !== strlen($bytes)) {
            throw InvalidPasteData::because('Salt inválido (se esperan 16 bytes en base64url).');
        }
        if ($iterations < self::MIN_ITERATIONS || $iterations > self::MAX_ITERATIONS) {
            throw InvalidPasteData::because(sprintf(
                'Iteraciones fuera de rango (%d a %d).',
                self::MIN_ITERATIONS,
                self::MAX_ITERATIONS,
            ));
        }

        return new self($salt, $iterations);
    }

    public function isConfigured(): bool
    {
        return isset($this->salt, $this->iterations);
    }
}