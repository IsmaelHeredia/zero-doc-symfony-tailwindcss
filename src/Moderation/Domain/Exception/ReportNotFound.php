<?php

declare(strict_types=1);

namespace App\Moderation\Domain\Exception;

use App\Moderation\Domain\ValueObject\ReportId;

final class ReportNotFound extends \DomainException
{
    public static function withId(ReportId $id): self
    {
        return new self(sprintf('Reporte %s no encontrado.', $id->value));
    }
}