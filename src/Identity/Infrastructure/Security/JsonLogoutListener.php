<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class JsonLogoutListener
{
    #[AsEventListener]
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new Response(null, 204, ['Cache-Control' => 'no-store']));
    }
}