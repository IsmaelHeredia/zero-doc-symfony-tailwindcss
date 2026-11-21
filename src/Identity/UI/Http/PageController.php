<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

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

    #[Route('/login', name: 'identity_login_page', methods: ['GET'])]
    public function login(): Response
    {
        return $this->pages->respond('identity/login.html');
    }

    #[Route('/register', name: 'identity_register_page', methods: ['GET'])]
    public function register(): Response
    {
        return $this->pages->respond('identity/register.html');
    }

    #[Route('/app/admin', name: 'identity_admin_page', methods: ['GET'])]
    public function admin(): Response
    {
        return $this->pages->respond('app/admin.html');
    }

    #[Route('/app/audit', name: 'identity_audit_page', methods: ['GET'])]
    public function audit(): Response
    {
        return $this->pages->respond('app/audit.html');
    }

}