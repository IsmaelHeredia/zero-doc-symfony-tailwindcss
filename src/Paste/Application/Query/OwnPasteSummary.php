<?php

declare(strict_types=1);

namespace App\Paste\Application\Query;

final readonly class OwnPasteSummary
{
    public function __construct(
        public string $id,
        public string $createdAt,
        public ?string $expiresAt,
        public int $views,
        public ?int $maxViews,
        public bool $burnAfterRead,
        public bool $hasPassword,
    ) {
    }
}