<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\ValueObject\RoleName;

final class RoleNotFound extends \DomainException
{
    public static function withName(RoleName $name): self
    {
        return new self(sprintf('El rol "%s" no existe.', $name->value));
    }
}