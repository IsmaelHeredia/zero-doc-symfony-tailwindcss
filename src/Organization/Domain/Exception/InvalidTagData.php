<?php

declare(strict_types=1);

namespace App\Organization\Domain\Exception;

final class InvalidTagData extends \InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}