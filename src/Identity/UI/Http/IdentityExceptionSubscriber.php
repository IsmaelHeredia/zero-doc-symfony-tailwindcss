<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Admin\Exception\AdminActionNotAllowed;
use App\Identity\Domain\Exception\CannotRemoveLastAdmin;
use App\Identity\Domain\Exception\CannotRevokeProtectedPermission;
use App\Identity\Domain\Exception\EmailAlreadyRegistered;
use App\Identity\Domain\Exception\InvalidRoleData;
use App\Identity\Domain\Exception\InvalidUserData;
use App\Identity\Domain\Exception\RoleAlreadyExists;
use App\Identity\Domain\Exception\RoleNotFound;
use App\Identity\Domain\Exception\UserNotFound;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class IdentityExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();

        $mapped = match (true) {
            $e instanceof InvalidUserData, $e instanceof InvalidRoleData => [422, 'invalid', $e->getMessage()],
            $e instanceof EmailAlreadyRegistered, $e instanceof RoleAlreadyExists, $e instanceof CannotRemoveLastAdmin,
            $e instanceof CannotRevokeProtectedPermission => [409, 'conflict', $e->getMessage()],
            $e instanceof RoleNotFound, $e instanceof UserNotFound => [404, 'not_found', $e->getMessage()],
            $e instanceof AdminActionNotAllowed => [403, 'forbidden', 'No autorizado.'],
            default => null,
        };
        if (null === $mapped) {
            return;
        }

        [$status, $code, $message] = $mapped;
        $event->setResponse(new JsonResponse(['error' => $code, 'message' => $message], $status, ['Cache-Control' => 'no-store']));
    }
}