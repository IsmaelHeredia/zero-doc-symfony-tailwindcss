<?php

declare(strict_types=1);

namespace App\Paste\Domain\ValueObject;

use App\Paste\Domain\Exception\InvalidPasteData;
use App\Shared\Domain\Base64Url;

final readonly class InitializationVector
{
    private const BYTES = 12;

    private function __construct(public string $value)
    {
    }

    public static function fromBase64Url(string $value): self
    {
        $bytes = Base64Url::decode($value);
        if (null === $bytes || self::BYTES !== strlen($bytes)) {
            throw InvalidPasteData::because('IV inválido (se esperan 12 bytes).');
        }

        return new self($value);
    }
}