<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Persistence\Doctrine;

use App\Paste\Application\Query\PasteAccessInfo;
use App\Paste\Application\Query\PasteAccessReader;
use App\Paste\Domain\ValueObject\PasteId;
use Doctrine\DBAL\Connection;

final class DbalPasteAccessReader implements PasteAccessReader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function find(PasteId $id, \DateTimeImmutable $now): ?PasteAccessInfo
    {
        $row = $this->connection->fetchAssociative(
            'SELECT kdf_salt, kdf_iterations FROM paste WHERE id = ? AND (expires_at IS NULL OR expires_at > ?)',
            [$id->value, $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')],
        );
        if (false === $row) {
            return null;
        }

        return new PasteAccessInfo(
            null === $row['kdf_salt'] ? null : (string) $row['kdf_salt'],
            null === $row['kdf_iterations'] ? null : (int) $row['kdf_iterations'],
        );
    }
}