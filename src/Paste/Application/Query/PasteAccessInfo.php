<?php

declare(strict_types=1);

namespace App\Paste\Application\Query;

final readonly class PasteAccessInfo
{
    public function __construct(public ?string $kdfSalt, public ?int $kdfIterations)
    {
    }

    public function hasPassword(): bool
    {
        return null !== $this->kdfSalt;
    }
}