<?php

declare(strict_types=1);

namespace App\Organization\Application\Attach;

final readonly class AttachPasteToTagCommand
{
    public function __construct(public string $tagId, public string $pasteId)
    {
    }
}