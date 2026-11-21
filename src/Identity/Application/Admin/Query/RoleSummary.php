<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Query;

final readonly class RoleSummary
{
    public function __construct(public string $name, public array $permissions)
    {
    }
}