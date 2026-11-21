<?php

declare(strict_types=1);

namespace App\Paste\Application\Delete;

final readonly class DeletePasteCommand
{
    public function __construct(public string $id, public ?string $deleteToken)
    {
    }
}