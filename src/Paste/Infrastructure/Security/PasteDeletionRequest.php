<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Security;

use App\Paste\Domain\Paste;
use App\Paste\Domain\ValueObject\DeleteToken;

final readonly class PasteDeletionRequest
{
    public function __construct(public Paste $paste, public ?DeleteToken $token)
    {
    }
}