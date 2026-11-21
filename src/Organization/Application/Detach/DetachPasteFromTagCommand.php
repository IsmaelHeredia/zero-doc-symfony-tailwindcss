<?php

declare(strict_types=1);

namespace App\Organization\Application\Detach;

final readonly class DetachPasteFromTagCommand
{
    public function __construct(public string $tagId, public string $pasteId)
    {
    }
}