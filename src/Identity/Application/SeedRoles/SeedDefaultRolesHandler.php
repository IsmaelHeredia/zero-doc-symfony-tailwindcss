<?php

declare(strict_types=1);

namespace App\Identity\Application\SeedRoles;

use App\Identity\Domain\Role;
use App\Identity\Domain\RoleRepository;
use App\Identity\Domain\ValueObject\PermissionCode;
use App\Identity\Domain\ValueObject\RoleId;
use App\Identity\Domain\ValueObject\RoleName;
use App\Shared\Application\EventBus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SeedDefaultRolesHandler
{
    public function __construct(
        private RoleRepository $roles,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SeedDefaultRolesCommand $command): void
    {
        $now = $this->clock->now();

        foreach (DefaultRoles::definition() as $name => $permissions) {
            $roleName = RoleName::fromString($name);
            $role = $this->roles->findByName($roleName) ?? Role::create(RoleId::generate(), $roleName);

            foreach ($permissions as $code) {
                $role->grant(PermissionCode::fromString($code), $now);
            }

            $this->roles->save($role);
            $this->events->publish(...$role->pullDomainEvents());
        }
    }
}