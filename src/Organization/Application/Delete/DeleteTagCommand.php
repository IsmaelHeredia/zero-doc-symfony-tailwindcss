<?php

declare(strict_types=1);

namespace App\Organization\Application\Delete;

final readonly class DeleteTagCommand
{
    public function __construct(public string $tagId)
    {
    }
}