<?php

declare(strict_types=1);

namespace App\Organization\UI\Http;

use App\Organization\Application\Exception\PasteNotOwnedByActor;
use App\Organization\Application\Exception\TagActionNotAllowed;
use App\Organization\Application\Exception\TagLimitReached;
use App\Organization\Domain\Exception\InvalidTagData;
use App\Organization\Domain\Exception\TagAlreadyExists;
use App\Organization\Domain\Exception\TagFull;
use App\Organization\Domain\Exception\TagNotFound;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class OrganizationExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();

        $mapped = match (true) {
            $e instanceof InvalidTagData => [422, 'invalid', $e->getMessage()],
            $e instanceof TagAlreadyExists, $e instanceof TagLimitReached, $e instanceof TagFull => [409, 'conflict', $e->getMessage()],
            $e instanceof TagNotFound, $e instanceof PasteNotOwnedByActor => [404, 'not_found', $e instanceof TagNotFound ? 'El tag no existe.' : $e->getMessage()],
            $e instanceof TagActionNotAllowed => [403, 'forbidden', 'No autorizado.'],
            default => null,
        };
        if (null === $mapped) {
            return;
        }

        [$status, $code, $message] = $mapped;
        $event->setResponse(new JsonResponse(['error' => $code, 'message' => $message], $status, ['Cache-Control' => 'no-store']));
    }
}