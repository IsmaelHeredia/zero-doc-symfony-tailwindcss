<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PagesTest extends WebTestCase
{
    public static function pages(): iterable
    {
        foreach (['/', '/login', '/register', '/mine', '/app/tags', '/app/moderation', '/app/admin', '/app/audit'] as $page) {
            yield $page => [$page];
        }
    }

    #[DataProvider('pages')]
    public function test_every_page_is_static_html_with_the_strict_csp(string $page): void
    {
        $client = static::createClient();

        $client->request('GET', $page);

        self::assertResponseStatusCodeSame(200);
        $csp = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringContainsString("script-src 'self'", $csp);
        self::assertStringContainsString("default-src 'none'", $csp);
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('type="module"', $client->getResponse()->getContent());
    }
}