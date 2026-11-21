<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

final class CannotRemoveLastAdmin extends \DomainException
{
    public static function create(): self
    {
        return new self('No se puede quitar el rol admin al último administrador.');
    }
}