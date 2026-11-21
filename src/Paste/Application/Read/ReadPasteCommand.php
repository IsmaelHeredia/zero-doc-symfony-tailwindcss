<?php

declare(strict_types=1);

namespace App\Paste\Application\Read;

final readonly class ReadPasteCommand
{
    public function __construct(public string $id)
    {
    }
}