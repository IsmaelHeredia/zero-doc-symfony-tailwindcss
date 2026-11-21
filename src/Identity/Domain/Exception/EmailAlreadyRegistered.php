<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

final class EmailAlreadyRegistered extends \DomainException
{
    public static function create(): self
    {
        return new self('Ya existe una cuenta con ese email.');
    }
}