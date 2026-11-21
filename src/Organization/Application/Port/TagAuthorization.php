<?php

declare(strict_types=1);

namespace App\Organization\Application\Port;

use App\Organization\Application\Exception\TagActionNotAllowed;
use App\Shared\Domain\UserId;

interface TagAuthorization
{
    public function requireManage(): UserId;
    public function requireListOwnPastes(): UserId;
}