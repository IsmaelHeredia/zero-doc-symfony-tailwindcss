<?php

declare(strict_types=1);

namespace App\Paste\Application\Create;

final readonly class CreatedPaste
{
    public function __construct(public string $id, public ?string $deleteToken)
    {
    }
}