<?php

declare(strict_types=1);

namespace App\Tests\Moderation\Support;

use App\Moderation\Application\Port\PasteExistence;
use App\Paste\Domain\ValueObject\PasteId;

final class StubPasteExistence implements PasteExistence
{
    private array $existing = [];

    public function with(PasteId $id): self
    {
        $this->existing[] = $id->value;

        return $this;
    }

    public function exists(PasteId $pasteId): bool
    {
        return in_array($pasteId->value, $this->existing, true);
    }
}