<?php

declare(strict_types=1);

namespace App\Paste\Application\Exception;

final class ListingNotAllowed extends \DomainException
{
    public static function create(): self
    {
        return new self('No autorizado.');
    }
}