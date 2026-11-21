<?php

declare(strict_types=1);

namespace App\Paste\Application\Create;

final readonly class CreatePasteCommand
{
    public function __construct(
        public string $ciphertext,
        public string $iv,
        public ?int $ttlSeconds,
        public bool $burnAfterRead,
        public ?int $maxViews,
        public ?string $kdfSalt = null,
        public ?int $kdfIterations = null,
    ) {
    }
}