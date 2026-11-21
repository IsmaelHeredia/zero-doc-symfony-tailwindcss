<?php

declare(strict_types=1);

namespace App\Moderation\Domain\Exception;

final class ReportAlreadyClosed extends \DomainException
{
    public static function create(): self
    {
        return new self('El reporte ya estaba cerrado.');
    }
}