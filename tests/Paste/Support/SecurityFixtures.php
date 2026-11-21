<?php

declare(strict_types=1);

namespace App\Tests\Paste\Support;

use App\Identity\Application\Query\UserAccess;
use App\Identity\Domain\User;
use App\Identity\Domain\ValueObject\Email;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\UserId;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class SecurityFixtures
{
    public static function user(array $permissions = [], ?UserId $id = null): SecurityUser
    {
        $domainUser = User::register(
            $id ?? UserId::generate(),
            Email::fromString(sprintf('u-%s@example.test', bin2hex(random_bytes(4)))),
            HashedPassword::fromHash('x'),
            new \DateTimeImmutable('2026-10-13 12:00:00'),
        );

        return SecurityUser::fromDomain($domainUser, new UserAccess([], $permissions));
    }

    public static function token(SecurityUser $user): TokenInterface
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}