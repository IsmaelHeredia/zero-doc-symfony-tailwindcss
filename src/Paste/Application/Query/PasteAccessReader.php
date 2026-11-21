<?php

declare(strict_types=1);

namespace App\Paste\Application\Query;

use App\Paste\Domain\ValueObject\PasteId;

interface PasteAccessReader
{
    public function find(PasteId $id, \DateTimeImmutable $now): ?PasteAccessInfo;
}