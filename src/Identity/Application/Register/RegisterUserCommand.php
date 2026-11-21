<?php

declare(strict_types=1);

namespace App\Identity\Application\Register;

final readonly class RegisterUserCommand
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter] public string $password,
        public array $extraRoles = [],
    ) {
    }
}