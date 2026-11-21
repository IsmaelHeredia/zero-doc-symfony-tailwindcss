<?php

declare(strict_types=1);

namespace App\Moderation\Domain\Exception;

final class ReportAlreadyFiled extends \DomainException
{
    public static function create(): self
    {
        return new self('Ya reportaste este paste.');
    }
}