<?php

declare(strict_types=1);

namespace App\Moderation\Domain\Exception;

final class InvalidReportData extends \InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}