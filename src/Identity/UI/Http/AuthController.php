<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Register\RegisterUserCommand;
use App\Identity\Domain\Exception\InvalidUserData;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final class AuthController
{
    use HandleTrait;

    private const NO_STORE = ['Cache-Control' => 'no-store'];

    public function __construct(MessageBusInterface $commandBus)
    {
        $this->messageBus = $commandBus;
    }

    #[Route('/auth/register', name: 'identity_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidUserData::because('JSON inválido.');
        }
        if (!is_array($data) || !is_string($data['email'] ?? null) || !is_string($data['password'] ?? null)) {
            throw InvalidUserData::because('Se requieren email y password.');
        }

        $id = $this->handle(new RegisterUserCommand($data['email'], $data['password']));

        return new JsonResponse(['id' => $id], 201, self::NO_STORE);
    }

    #[Route('/auth/login', name: 'identity_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?SecurityUser $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['error' => 'invalid_credentials', 'message' => 'Credenciales inválidas.'], 401, self::NO_STORE);
        }

        return new JsonResponse(['id' => $user->id, 'email' => $user->email], 200, self::NO_STORE);
    }

    #[Route('/auth/logout', name: 'identity_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Interceptado por el firewall.');
    }

    #[Route('/auth/me', name: 'identity_me', methods: ['GET'])]
    public function me(#[CurrentUser] SecurityUser $user): JsonResponse
    {
        return new JsonResponse([
            'id' => $user->id,
            'email' => $user->email,
            'roles' => $user->roleNames(),
            'permissions' => $user->permissions(),
        ], 200, self::NO_STORE);
    }
}