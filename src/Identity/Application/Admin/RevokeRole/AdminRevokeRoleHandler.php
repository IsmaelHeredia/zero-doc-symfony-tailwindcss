<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\RevokeRole;

use App\Identity\Application\Admin\Audit\AdminAction;
use App\Identity\Application\Admin\Audit\AdminAuditLog;
use App\Identity\Application\Admin\AdminAuthorization;
use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\LastAdminGuard;
use App\Identity\Domain\RoleRepository;
use App\Identity\Domain\UserRepository;
use App\Identity\Domain\ValueObject\RoleName;
use App\Shared\Application\EventBus;
use App\Shared\Domain\UserId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AdminRevokeRoleHandler
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private LastAdminGuard $guard,
        private AdminAuthorization $authorization,
        private AdminAuditLog $audit,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AdminRevokeRoleCommand $command): void
    {
        $actor = $this->authorization->requireUserManage();

        $role = $this->roles->getByNameForUpdate(RoleName::fromString($command->roleName));
        $userId = UserId::fromString($command->userId);
        $user = $this->users->findById($userId) ?? throw UserNotFound::withId($userId);
        $now = $this->clock->now();

        $this->guard->assertCanRevoke($user, $role);
        $user->revokeRole($role->id(), $now);

        $events = $user->pullDomainEvents();
        if ([] === $events) {
            return;
        }

        $this->users->save($user);
        $this->audit->record(AdminAction::RoleRevoked, $actor, $now, $role->name()->value, $userId);
        $this->events->publish(...$events);
    }
}