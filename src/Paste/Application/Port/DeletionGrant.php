<?php

declare(strict_types=1);

namespace App\Paste\Application\Port;

use App\Paste\Domain\DeletionReason;
use App\Shared\Domain\UserId;

final readonly class DeletionGrant
{
    public function __construct(public DeletionReason $reason, public ?UserId $actor = null)
    {
    }
}