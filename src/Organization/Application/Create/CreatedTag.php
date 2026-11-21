<?php

declare(strict_types=1);

namespace App\Organization\Application\Create;

final readonly class CreatedTag
{
    public function __construct(public string $id, public string $name)
    {
    }
}