<?php

declare(strict_types=1);

namespace App\Paste\Domain\ValueObject;

use App\Paste\Domain\Exception\InvalidPasteData;
use App\Shared\Domain\Base64Url;

final readonly class Ciphertext
{
    private const GCM_TAG_BYTES = 16;

    private function __construct(public string $value)
    {
    }

    public static function fromBase64Url(string $value): self
    {
        $bytes = Base64Url::decode($value);
        if (null === $bytes || strlen($bytes) < self::GCM_TAG_BYTES) {
            throw InvalidPasteData::because('Ciphertext inválido.');
        }

        return new self($value);
    }

    public function sizeInBytes(): int
    {
        return intdiv(strlen($this->value) * 3, 4);
    }
}