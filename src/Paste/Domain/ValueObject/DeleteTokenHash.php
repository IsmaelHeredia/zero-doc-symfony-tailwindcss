<?php

declare(strict_types=1);

namespace App\Paste\Domain\ValueObject;

use App\Paste\Domain\Exception\InvalidPasteData;

final readonly class DeleteTokenHash
{
    private function __construct(public string $value)
    {
    }

    public static function fromToken(DeleteToken $token): self
    {
        return new self(hash('sha256', $token->value));
    }

    public static function fromString(string $hex): self
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/', $hex)) {
            throw InvalidPasteData::because('Hash de token inválido.');
        }

        return new self($hex);
    }

    public function matches(DeleteToken $candidate): bool
    {
        return hash_equals($this->value, hash('sha256', $candidate->value));
    }
}