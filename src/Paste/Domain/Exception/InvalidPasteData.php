<?php

declare(strict_types=1);

namespace App\Paste\Domain\Exception;

final class InvalidPasteData extends \InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}