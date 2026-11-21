<?php

declare(strict_types=1);

namespace App\Paste\Application\Query;

final readonly class OwnPastesPage
{
    public function __construct(public array $pastes, public bool $truncated)
    {
    }
}