<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin;

use App\Identity\Application\Admin\Exception\AdminActionNotAllowed;
use App\Shared\Domain\UserId;

interface AdminAuthorization
{
    public function requireUserManage(): UserId;

    public function requireRoleManage(): UserId;

    public function requireAuditRead(): UserId;
}