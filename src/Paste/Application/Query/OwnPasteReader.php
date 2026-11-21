<?php

declare(strict_types=1);

namespace App\Paste\Application\Query;

use App\Shared\Domain\UserId;

interface OwnPasteReader
{
    public function pastesOf(UserId $owner, \DateTimeImmutable $now, int $limit): OwnPastesPage;
}