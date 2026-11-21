<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Query;

final readonly class UserListing
{
    public function __construct(public array $users, public bool $truncated, public array $availableRoles)
    {
    }
}