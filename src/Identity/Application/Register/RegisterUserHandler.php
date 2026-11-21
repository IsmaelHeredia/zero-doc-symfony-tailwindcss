<?php

declare(strict_types=1);

namespace App\Identity\Application\Register;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Exception\EmailAlreadyRegistered;
use App\Identity\Domain\RoleRepository;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\PlainPassword;
use App\Identity\Domain\ValueObject\RoleName;
use App\Shared\Application\EventBus;
use App\Shared\Domain\UserId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private PasswordHasher $hasher,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegisterUserCommand $command): string
    {
        $email = Email::fromString($command->email);
        $password = PlainPassword::fromString($command->password);
        $now = $this->clock->now();

        if (null !== $this->users->findByEmail($email)) {
            throw EmailAlreadyRegistered::create();
        }

        $memberRole = $this->roles->findByName(RoleName::member())
            ?? throw new \LogicException('Roles no inicializados: ejecuta "php bin/console app:identity:seed".');

        $user = User::register(UserId::generate(), $email, $this->hasher->hash($password), $now);
        $user->assignRole($memberRole->id(), $now);
        foreach ($command->extraRoles as $roleName) {
            $user->assignRole($this->roles->getByName(RoleName::fromString($roleName))->id(), $now);
        }

        $this->users->save($user);
        $this->events->publish(...$user->pullDomainEvents());

        return $user->id()->value;
    }
}