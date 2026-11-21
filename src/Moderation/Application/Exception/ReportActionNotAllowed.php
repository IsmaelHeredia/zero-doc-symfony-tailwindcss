<?php

declare(strict_types=1);

namespace App\Moderation\Application\Exception;

final class ReportActionNotAllowed extends \DomainException
{
    public static function create(): self
    {
        return new self('No autorizado.');
    }
}