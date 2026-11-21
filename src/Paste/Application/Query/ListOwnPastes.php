<?php

declare(strict_types=1);

namespace App\Paste\Application\Query;

use App\Paste\Application\Port\PasteListingAuthorization;
use Psr\Clock\ClockInterface;

final readonly class ListOwnPastes
{
    public const LIMIT = 200;

    public function __construct(
        private PasteListingAuthorization $authorization,
        private OwnPasteReader $reader,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(): OwnPastesPage
    {
        $owner = $this->authorization->requireListOwn();

        return $this->reader->pastesOf($owner, $this->clock->now(), self::LIMIT);
    }
}