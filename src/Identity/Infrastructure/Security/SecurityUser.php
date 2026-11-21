<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Query\UserAccess;
use App\Identity\Domain\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use App\Shared\Domain\UserId;
use App\Shared\Infrastructure\Security\AuthenticatedActor;

final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, AuthenticatedActor
{
    private function __construct(
        public readonly string $id,
        public readonly string $email,
        private readonly string $passwordHash,
        private readonly array $roleNames,
        private readonly array $permissions,
    ) {
    }

    public static function fromDomain(User $user, UserAccess $access): self
    {
        return new self(
            $user->id()->value,
            $user->email()->value,
            $user->passwordHash()->value,
            $access->roles,
            $access->permissions,
        );
    }

    public function getRoles(): array
    {
        return array_values(array_unique([
            'ROLE_USER',
            ...array_map(static fn (string $r): string => 'ROLE_'.strtoupper($r), $this->roleNames),
        ]));
    }

    public function roleNames(): array
    {
        return $this->roleNames;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    public function hasPermission(string $code): bool
    {
        return in_array($code, $this->permissions, true);
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0passwordHash"] = hash('crc32c', $this->passwordHash);

        return $data;
    }
    
    public function actorId(): UserId
    {
        return UserId::fromString($this->id);
    }

}