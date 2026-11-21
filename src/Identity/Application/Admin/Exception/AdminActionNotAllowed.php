<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Exception;

final class AdminActionNotAllowed extends \DomainException
{
    public static function create(): self
    {
        return new self('No autorizado.');
    }
}