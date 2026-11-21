<?php

declare(strict_types=1);

namespace App\Paste\Application\Query;

final readonly class DeletionLogEntry
{
    public function __construct(
        public string $deletedAt,
        public string $pasteId,
        public string $reason,
        public ?string $actorId,
    ) {
    }
}