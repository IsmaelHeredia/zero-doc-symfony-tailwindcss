<?php

declare(strict_types=1);

namespace App\Tests\Identity\Support;

use App\Identity\Application\Admin\AdminAuthorization;
use App\Identity\Application\Admin\Exception\AdminActionNotAllowed;
use App\Shared\Domain\UserId;

final class StubAdminAuthorization implements AdminAuthorization
{
    public function __construct(
        private readonly UserId $actor,
        private readonly bool $canUsers = false,
        private readonly bool $canRoles = false,
        private readonly bool $canAudit = false,
    ) {
    }

    public function requireAuditRead(): UserId
    {
        return $this->canAudit ? $this->actor : throw AdminActionNotAllowed::create();
    }

    public function requireUserManage(): UserId
    {
        return $this->canUsers ? $this->actor : throw AdminActionNotAllowed::create();
    }

    public function requireRoleManage(): UserId
    {
        return $this->canRoles ? $this->actor : throw AdminActionNotAllowed::create();
    }
}