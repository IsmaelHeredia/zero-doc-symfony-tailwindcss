<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Application\Port\PasteOwnership;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;
use Doctrine\DBAL\Connection;

final class DbalPasteOwnership implements PasteOwnership
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function isOwnedBy(PasteId $pasteId, UserId $owner): bool
    {
        $ownerId = $this->connection->fetchOne('SELECT owner_id FROM paste WHERE id = ? FOR SHARE', [$pasteId->value]);

        return \is_string($ownerId) && $ownerId === $owner->value;
    }
}