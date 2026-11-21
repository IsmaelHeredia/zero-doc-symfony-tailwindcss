<?php

declare(strict_types=1);

namespace App\Paste\UI\Http;

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

    #[Route('/', name: 'page_create', methods: ['GET'])]
    public function create(): Response
    {
        return $this->pages->respond('paste/create.html');
    }

    #[Route('/p/{id}', name: 'page_view', requirements: ['id' => '[A-Za-z0-9_-]{22}'], methods: ['GET'])]
    public function view(): Response
    {
        return $this->pages->respond('paste/view.html');
    }

    #[Route('/mine', name: 'page_mine', methods: ['GET'])]
    public function mine(): Response
    {
        return $this->pages->respond('paste/mine.html');
    }
}