<?php

declare(strict_types=1);

namespace App\Tests\Paste\Support;

use App\Paste\Application\Exception\ListingNotAllowed;
use App\Paste\Application\Port\PasteListingAuthorization;
use App\Shared\Domain\UserId;

final class StubPasteListingAuthorization implements PasteListingAuthorization
{
    public function __construct(private readonly ?UserId $actor)
    {
    }

    public function requireListOwn(): UserId
    {
        return $this->actor ?? throw ListingNotAllowed::create();
    }

    public function requireAuditRead(): UserId
    {
        return $this->actor ?? throw ListingNotAllowed::create();
    }
}