<?php

declare(strict_types=1);

namespace App\Organization\Application\Query;

final readonly class TaggedPaste
{
    public function __construct(
        public string $id,
        public string $createdAt,
        public ?string $expiresAt,
        public int $views,
    ) {
    }
}