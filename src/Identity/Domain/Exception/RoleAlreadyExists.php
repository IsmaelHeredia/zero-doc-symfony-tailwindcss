<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\ValueObject\RoleName;

final class RoleAlreadyExists extends \DomainException
{
    public static function withName(RoleName $name): self
    {
        return new self(sprintf('Ya existe un rol "%s".', $name->value));
    }
}