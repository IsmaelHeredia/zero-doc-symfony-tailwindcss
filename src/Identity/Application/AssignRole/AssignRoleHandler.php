<?php

declare(strict_types=1);

namespace App\Identity\Application\AssignRole;

use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\RoleRepository;
use App\Identity\Domain\UserRepository;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\RoleName;
use App\Shared\Application\EventBus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AssignRoleHandler
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AssignRoleCommand $command): void
    {
        $email = Email::fromString($command->email);
        $role = $this->roles->getByName(RoleName::fromString($command->roleName));
        $user = $this->users->findByEmail($email) ?? throw UserNotFound::withEmail($email);

        $user->assignRole($role->id(), $this->clock->now());

        $this->users->save($user);
        $this->events->publish(...$user->pullDomainEvents());
    }
}