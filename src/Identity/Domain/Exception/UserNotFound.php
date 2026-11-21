<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\ValueObject\Email;
use App\Shared\Domain\UserId;

final class UserNotFound extends \DomainException
{
    public static function withEmail(Email $email): self
    {
        return new self(sprintf('No existe un usuario con el email %s.', $email->value));
    }

    public static function withId(UserId $id): self
    {
        return new self('El usuario no existe.');
    }

}