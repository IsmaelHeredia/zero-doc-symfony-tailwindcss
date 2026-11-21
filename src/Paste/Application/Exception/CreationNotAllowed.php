<?php

declare(strict_types=1);

namespace App\Paste\Application\Exception;

final class CreationNotAllowed extends \DomainException
{
    public static function create(): self
    {
        return new self('Tu cuenta no tiene permiso para crear pastes.');
    }
}