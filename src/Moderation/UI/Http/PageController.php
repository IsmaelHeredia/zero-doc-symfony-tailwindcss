<?php

declare(strict_types=1);

namespace App\Moderation\UI\Http;

use App\Shared\UI\Http\StaticPageResponder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class PageController
{
    public function __construct(private StaticPageResponder $pages)
    {
    }

    #[Route('/app/moderation', name: 'moderation_page', methods: ['GET'])]
    public function moderation(): Response
    {
        return $this->pages->respond('app/moderation.html');
    }
}