<?php

declare(strict_types=1);

namespace App\Paste\Domain\Exception;

use App\Paste\Domain\ValueObject\PasteId;

final class PasteNotFound extends \DomainException
{
    public static function withId(PasteId $id): self
    {
        return new self(sprintf('Paste %s no encontrado.', $id->value));
    }
}