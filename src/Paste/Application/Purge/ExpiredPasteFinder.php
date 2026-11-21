<?php

declare(strict_types=1);

namespace App\Paste\Application\Purge;

interface ExpiredPasteFinder
{
    public function findExpiredIds(\DateTimeImmutable $now, int $limit): array;
}