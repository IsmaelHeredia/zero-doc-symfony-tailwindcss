<?php

declare(strict_types=1);

namespace App\Paste\Application\Audit;

use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;

interface DeletionLog
{
    public function record(PasteId $pasteId, DeletionReason $reason, ?UserId $actor, \DateTimeImmutable $deletedAt): void;
}