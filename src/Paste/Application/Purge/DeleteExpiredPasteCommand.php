<?php

declare(strict_types=1);

namespace App\Paste\Application\Purge;

final readonly class DeleteExpiredPasteCommand
{
    public function __construct(public string $id)
    {
    }
}