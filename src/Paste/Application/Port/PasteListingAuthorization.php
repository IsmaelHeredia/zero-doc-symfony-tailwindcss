<?php

declare(strict_types=1);

namespace App\Paste\Application\Port;

use App\Paste\Application\Exception\ListingNotAllowed;
use App\Shared\Domain\UserId;

interface PasteListingAuthorization
{
    public function requireListOwn(): UserId;

    public function requireAuditRead(): UserId;
}