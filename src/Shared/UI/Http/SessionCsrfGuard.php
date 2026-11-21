<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SessionCsrfGuard implements EventSubscriberInterface
{
    private const HEADER = 'X-Requested-With';
    private const VALUE = 'fetch';

    private const PROTECTED_PREFIXES = ['/pastes', '/tags', '/reports', '/moderation', '/admin'];

    public function __construct(private readonly TokenStorageInterface $tokens)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 0]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (
            !$event->isMainRequest()
            || $request->isMethodSafe()
            || !$this->isProtectedPath($request->getPathInfo())
            || !$this->tokens->getToken()?->getUser() instanceof UserInterface
            || self::VALUE === $request->headers->get(self::HEADER)
        ) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['error' => 'csrf', 'message' => 'Falta la cabecera X-Requested-With: fetch.'],
            403,
            ['Cache-Control' => 'no-store'],
        ));
    }

    private function isProtectedPath(string $path): bool
    {
        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

}