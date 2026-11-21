<?php

declare(strict_types=1);

namespace App\Organization\Domain\Exception;

final class TagFull extends \DomainException
{
    public static function create(): self
    {
        return new self('Este tag ya tiene el máximo de pastes permitido.');
    }
}