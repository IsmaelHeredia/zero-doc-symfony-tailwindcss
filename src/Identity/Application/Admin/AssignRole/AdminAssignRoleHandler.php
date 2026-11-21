<?php

declare(strict_types=1);

namespace App\Identity\Application\Admin\AssignRole;

use App\Identity\Application\Admin\Audit\AdminAction;
use App\Identity\Application\Admin\Audit\AdminAuditLog;
use App\Identity\Application\Admin\AdminAuthorization;
use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\RoleRepository;
use App\Identity\Domain\UserRepository;
use App\Identity\Domain\ValueObject\RoleName;
use App\Shared\Application\EventBus;
use App\Shared\Domain\UserId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AdminAssignRoleHandler
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private AdminAuthorization $authorization,
        private AdminAuditLog $audit,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AdminAssignRoleCommand $command): void
    {
        $actor = $this->authorization->requireUserManage();
        $role = $this->roles->getByName(RoleName::fromString($command->roleName));
        $userId = UserId::fromString($command->userId);
        $user = $this->users->findById($userId) ?? throw UserNotFound::withId($userId);
        $now = $this->clock->now();

        $user->assignRole($role->id(), $now);

        $events = $user->pullDomainEvents();
        if ([] === $events) {
            return;
        }

        $this->users->save($user);
        $this->audit->record(AdminAction::RoleAssigned, $actor, $now, $role->name()->value, $userId);
        $this->events->publish(...$events);
    }
}