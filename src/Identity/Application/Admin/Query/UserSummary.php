<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Query;

final readonly class UserSummary
{
    public function __construct(public string $id, public string $email, public array $roles, public string $createdAt)
    {
    }
}