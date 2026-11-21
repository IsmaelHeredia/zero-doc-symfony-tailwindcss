<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\RevokePermission;

use App\Identity\Application\Admin\Audit\AdminAction;
use App\Identity\Application\Admin\Audit\AdminAuditLog;
use App\Identity\Application\Admin\AdminAuthorization;
use App\Identity\Domain\RoleRepository;
use App\Identity\Domain\ValueObject\PermissionCode;
use App\Identity\Domain\ValueObject\RoleName;
use App\Shared\Application\EventBus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RevokePermissionHandler
{
    public function __construct(
        private RoleRepository $roles,
        private AdminAuthorization $authorization,
        private AdminAuditLog $audit,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokePermissionCommand $command): void
    {
        $actor = $this->authorization->requireRoleManage();
        $permission = PermissionCode::fromCatalog($command->permission);
        $role = $this->roles->getByNameForUpdate(RoleName::fromString($command->roleName));
        $now = $this->clock->now();

        $role->revoke($permission, $now);

        $events = $role->pullDomainEvents();
        if ([] === $events) {
            return;
        }

        $this->roles->save($role);
        $this->audit->record(AdminAction::PermissionRevoked, $actor, $now, $role->name()->value, null, $permission->value);
        $this->events->publish(...$events);
    }
}