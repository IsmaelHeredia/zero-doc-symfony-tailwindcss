<?php

declare(strict_types=1);

namespace App\Moderation\Domain\ValueObject;

enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}