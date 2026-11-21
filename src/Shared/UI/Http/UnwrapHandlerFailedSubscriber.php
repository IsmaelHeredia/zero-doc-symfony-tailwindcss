<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

final class UnwrapHandlerFailedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 16]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();

        while ($e instanceof HandlerFailedException && 1 === count($wrapped = $e->getWrappedExceptions())) {
            $e = reset($wrapped);
        }

        $event->setThrowable($e);
    }
}