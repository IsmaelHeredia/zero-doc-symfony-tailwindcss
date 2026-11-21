<?php

declare(strict_types=1);

namespace App\Paste\Domain\ValueObject;

use App\Paste\Domain\Exception\InvalidPasteData;

final readonly class ContentSizeLimit
{
    private function __construct(public int $maxBytes)
    {
    }

    public static function ofBytes(int $maxBytes): self
    {
        if ($maxBytes < 1) {
            throw InvalidPasteData::because('El límite de tamaño debe ser positivo.');
        }

        return new self($maxBytes);
    }

    public function allows(Ciphertext $ciphertext): bool
    {
        return $ciphertext->sizeInBytes() <= $this->maxBytes;
    }
}