<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Query\UserAccessReader;
use App\Identity\Domain\Exception\InvalidUserData;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Identity\Domain\ValueObject\Email;
use App\Shared\Domain\UserId;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class SecurityUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserAccessReader $access,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $user = $this->users->findByEmail(Email::fromString($identifier));
        } catch (InvalidUserData) {
            $user = null;
        }

        return $this->toSecurityUser($user ?? throw new UserNotFoundException());
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Clase no soportada: %s', $user::class));
        }

        $domainUser = $this->users->findById(UserId::fromString($user->id)) ?? throw new UserNotFoundException();

        return $this->toSecurityUser($domainUser);
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }

    private function toSecurityUser(User $user): SecurityUser
    {
        return SecurityUser::fromDomain($user, $this->access->forUser($user->id()));
    }
}