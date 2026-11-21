<?php

declare(strict_types=1);

namespace App\Organization\Application\Create;

final readonly class CreateTagCommand
{
    public function __construct(public string $name)
    {
    }
}