<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

final class InvalidRoleData extends \InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}