<?php

declare(strict_types=1);

namespace App\Organization\Application\Exception;

final class PasteNotOwnedByActor extends \DomainException
{
    public static function create(): self
    {
        return new self('Ese paste no existe entre tus pastes.');
    }
}