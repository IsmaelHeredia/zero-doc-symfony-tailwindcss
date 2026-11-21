<?php

declare(strict_types=1);

namespace App\Paste\Domain;

use App\Paste\Domain\ValueObject\Ciphertext;
use App\Paste\Domain\ValueObject\InitializationVector;
use App\Paste\Domain\ValueObject\KeyDerivation;

final readonly class EncryptedPayload
{
    public function __construct(
        public Ciphertext $ciphertext,
        public InitializationVector $iv,
        public ?KeyDerivation $keyDerivation = null,
    ) {
    }
}