<?php

declare(strict_types=1);

namespace App\Organization\Application\Query;

final readonly class TagSummary
{
    public function __construct(public string $id, public string $name, public int $pasteCount)
    {
    }
}