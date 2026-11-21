<?php

declare(strict_types=1);

namespace App\Tests\Paste\Support;

use App\Paste\Application\Audit\DeletionLog;
use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;

final class InMemoryDeletionLog implements DeletionLog
{
    public array $entries = [];

    public function record(PasteId $pasteId, DeletionReason $reason, ?UserId $actor, \DateTimeImmutable $deletedAt): void
    {
        $this->entries[] = ['pasteId' => $pasteId, 'reason' => $reason, 'actor' => $actor, 'at' => $deletedAt];
    }
}