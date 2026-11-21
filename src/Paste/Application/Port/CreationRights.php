<?php

declare(strict_types=1);

namespace App\Paste\Application\Port;

use App\Shared\Domain\UserId;

final readonly class CreationRights
{
    public function __construct(
        public ?UserId $actor,
        public bool $canCreate,
        public bool $canCreatePermanent,
        public bool $canCreateLarge,
    ) {
    }
}