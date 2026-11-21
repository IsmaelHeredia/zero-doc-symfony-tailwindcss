<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\Query;

use App\Identity\Application\Admin\AdminAuthorization;

final readonly class ListUsers
{
    public const LIMIT = 200;

    public function __construct(private AdminAuthorization $authorization, private UserDirectory $directory)
    {
    }

    public function __invoke(): UserListing
    {
        $this->authorization->requireUserManage();

        return $this->directory->users(self::LIMIT);
    }
}