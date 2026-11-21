<?php

declare(strict_types=1);

namespace App\Paste\Application\Exception;

use App\Paste\Domain\ValueObject\PasteId;

final class DeletionNotAllowed extends \DomainException
{
    public static function forPaste(PasteId $id): self
    {
        return new self(sprintf('No autorizado a borrar el paste %s.', $id->value));
    }
}