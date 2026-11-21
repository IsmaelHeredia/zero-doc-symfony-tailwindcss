<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

final class ContentSecurityPolicy
{
    public const PAGE = "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'";
    public const API = "default-src 'none'; frame-ancestors 'none'";
}