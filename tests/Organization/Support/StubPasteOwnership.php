<?php

declare(strict_types=1);

namespace App\Tests\Organization\Support;

use App\Organization\Application\Port\PasteOwnership;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;

final class StubPasteOwnership implements PasteOwnership
{
    private array $owned = [];

    public function owns(UserId $user, PasteId $paste): self
    {
        $this->owned[] = $user->value.'|'.$paste->value;

        return $this;
    }

    public function isOwnedBy(PasteId $pasteId, UserId $owner): bool
    {
        return in_array($owner->value.'|'.$pasteId->value, $this->owned, true);
    }
}