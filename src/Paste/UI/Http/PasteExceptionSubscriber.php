<?php

declare(strict_types=1);

namespace App\Paste\UI\Http;

use App\Paste\Application\Exception\CreationNotAllowed;
use App\Paste\Application\Exception\DeletionNotAllowed;
use App\Paste\Application\Exception\ListingNotAllowed;
use App\Paste\Domain\Exception\InvalidPasteData;
use App\Paste\Domain\Exception\PasteConsumed;
use App\Paste\Domain\Exception\PasteExpired;
use App\Paste\Domain\Exception\PasteNotFound;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class PasteExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();

        $mapped = match (true) {
            $e instanceof InvalidPasteData => [422, 'invalid', $e->getMessage()],
            $e instanceof PasteNotFound, $e instanceof PasteExpired, $e instanceof PasteConsumed
                => [404, 'not_found', 'El paste no existe, expiró o ya fue leído.'],
            $e instanceof DeletionNotAllowed, $e instanceof ListingNotAllowed => [403, 'forbidden', 'No autorizado.'],
            $e instanceof CreationNotAllowed => [403, 'forbidden', $e->getMessage()],
            default => null,
        };
        if (null === $mapped) {
            return;
        }

        [$status, $code, $message] = $mapped;
        $event->setResponse(new JsonResponse(['error' => $code, 'message' => $message], $status, ['Cache-Control' => 'no-store']));
    }
}