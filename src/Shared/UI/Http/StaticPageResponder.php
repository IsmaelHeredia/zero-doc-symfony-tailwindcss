<?php
declare(strict_types=1);

namespace App\Shared\UI\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

final readonly class StaticPageResponder
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    public function respond(string $template): Response
    {
        $response = new Response((string) file_get_contents($this->projectDir.'/templates/'.$template));
        $response->headers->add([
            'Content-Security-Policy' => ContentSecurityPolicy::PAGE,
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);

        return $response;
    }
}