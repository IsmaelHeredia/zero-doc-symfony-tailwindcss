<?php

declare(strict_types=1);

namespace App\Paste\Domain;

enum DeletionReason: string
{
    case Owner = 'owner';
    case Moderator = 'moderator';
    case Token = 'token';
    case Expired = 'expired';
    case Burned = 'burned';
}