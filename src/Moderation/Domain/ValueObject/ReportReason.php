<?php

declare(strict_types=1);

namespace App\Moderation\Domain\ValueObject;

enum ReportReason: string
{
    case Spam = 'spam';
    case Malware = 'malware';
    case Illegal = 'illegal';
    case Harassment = 'harassment';
    case Other = 'other';
}