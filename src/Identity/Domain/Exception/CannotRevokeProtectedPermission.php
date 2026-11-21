<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

final class CannotRevokeProtectedPermission extends \DomainException
{
    public static function create(): self
    {
        return new self('El rol admin conserva siempre user.manage y role.manage.');
    }
}