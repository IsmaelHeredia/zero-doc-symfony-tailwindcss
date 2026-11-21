<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Query;

interface UserDirectory
{
    public function users(int $limit): UserListing;
}