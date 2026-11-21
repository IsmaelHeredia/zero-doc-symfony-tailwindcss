<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\PlainPassword;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private readonly PasswordHasherFactoryInterface $factory)
    {
    }

    public function hash(PlainPassword $plain): HashedPassword
    {
        return HashedPassword::fromHash(
            $this->factory->getPasswordHasher(SecurityUser::class)->hash($plain->value),
        );
    }
}