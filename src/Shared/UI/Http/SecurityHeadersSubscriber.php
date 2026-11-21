<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public const BASELINE = [
        'Content-Security-Policy' => ContentSecurityPolicy::API,
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
        'X-Frame-Options' => 'DENY',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
    ];

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -10]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        foreach (self::BASELINE as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }
    }
}