<?php

declare(strict_types=1);

namespace App\Moderation\Application\Port;

use App\Paste\Domain\ValueObject\PasteId;

interface PasteExistence
{
    public function exists(PasteId $pasteId): bool;
}