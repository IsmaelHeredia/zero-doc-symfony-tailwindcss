<?php

declare(strict_types=1);

namespace App\Organization\Application\Port;

use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;

interface PasteOwnership
{
    public function isOwnedBy(PasteId $pasteId, UserId $owner): bool;
}