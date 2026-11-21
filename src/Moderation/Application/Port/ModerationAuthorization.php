<?php

declare(strict_types=1);

namespace App\Moderation\Application\Port;

use App\Moderation\Application\Exception\ReportActionNotAllowed;
use App\Shared\Domain\UserId;

interface ModerationAuthorization
{
    public function reporter(): ?UserId;
    public function requireReview(): UserId;
}