<?php

declare(strict_types=1);

namespace App\Paste\Application\Query;

use App\Paste\Application\Port\PasteListingAuthorization;

final readonly class ListPasteDeletions
{
    public const LIMIT = 200;

    public function __construct(private PasteListingAuthorization $authorization, private DeletionLogReader $reader)
    {
    }

    public function __invoke(): array
    {
        $this->authorization->requireAuditRead();

        return $this->reader->latest(self::LIMIT);
    }
}