<?php

declare(strict_types=1);

namespace App\Paste\Application\Query;

interface DeletionLogReader
{
    public function latest(int $limit): array;
}