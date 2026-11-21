<?php

declare(strict_types=1);

namespace App\Tests\Moderation\Support;

use App\Moderation\Application\Exception\ReportActionNotAllowed;
use App\Moderation\Application\Port\ModerationAuthorization;
use App\Shared\Domain\UserId;

final class StubModerationAuthorization implements ModerationAuthorization
{
    public function __construct(private readonly ?UserId $actor = null, private readonly bool $canReview = false)
    {
    }

    public function reporter(): ?UserId
    {
        return $this->actor;
    }

    public function requireReview(): UserId
    {
        return $this->canReview && null !== $this->actor ? $this->actor : throw ReportActionNotAllowed::create();
    }
}