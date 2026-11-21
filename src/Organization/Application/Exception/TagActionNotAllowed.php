<?php

declare(strict_types=1);

namespace App\Organization\Application\Exception;

final class TagActionNotAllowed extends \DomainException
{
    public static function create(): self
    {
        return new self('No autorizado.');
    }
}