<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Persistence\Doctrine;

use App\Paste\Application\Audit\DeletionLog;
use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;
use Doctrine\DBAL\Connection;

final class DbalDeletionLog implements DeletionLog
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function record(PasteId $pasteId, DeletionReason $reason, ?UserId $actor, \DateTimeImmutable $deletedAt): void
    {
        $this->connection->insert('paste_deletion_log', [
            'paste_id' => $pasteId->value,
            'deleted_by_user_id' => $actor?->value,
            'reason' => $reason->value,
            'deleted_at' => $deletedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }
}