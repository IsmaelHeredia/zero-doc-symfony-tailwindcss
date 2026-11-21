<?php

declare(strict_types=1);

namespace App\Moderation\Infrastructure\Persistence\Doctrine;

use App\Moderation\Application\Port\PasteExistence;
use App\Paste\Domain\ValueObject\PasteId;
use Doctrine\DBAL\Connection;

final class DbalPasteExistence implements PasteExistence
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function exists(PasteId $pasteId): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM paste WHERE id = ? FOR SHARE', [$pasteId->value]);
    }
}