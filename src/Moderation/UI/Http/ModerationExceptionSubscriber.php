<?php

declare(strict_types=1);

namespace App\Moderation\UI\Http;

use App\Moderation\Application\Exception\ReportActionNotAllowed;
use App\Moderation\Domain\Exception\InvalidReportData;
use App\Moderation\Domain\Exception\ReportAlreadyClosed;
use App\Moderation\Domain\Exception\ReportAlreadyFiled;
use App\Moderation\Domain\Exception\ReportNotFound;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ModerationExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();

        if ($e instanceof ReportAlreadyFiled) {
            $event->setResponse(new JsonResponse(['status' => 'received'], 202, ['Cache-Control' => 'no-store']));

            return;
        }

        $mapped = match (true) {
            $e instanceof InvalidReportData => [422, 'invalid', $e->getMessage()],
            $e instanceof ReportNotFound => [404, 'not_found', 'El reporte no existe.'],
            $e instanceof ReportAlreadyClosed => [409, 'conflict', $e->getMessage()],
            $e instanceof ReportActionNotAllowed => [403, 'forbidden', 'No autorizado.'],
            default => null,
        };
        if (null === $mapped) {
            return;
        }

        [$status, $code, $message] = $mapped;
        $event->setResponse(new JsonResponse(['error' => $code, 'message' => $message], $status, ['Cache-Control' => 'no-store']));
    }
}