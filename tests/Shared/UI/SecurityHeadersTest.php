<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI;

use App\Shared\Domain\Base64Url;
use App\Shared\UI\Http\ContentSecurityPolicy;
use App\Shared\UI\Http\SecurityHeadersSubscriber;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityHeadersTest extends WebTestCase
{
    public function test_json_responses_get_a_locked_down_baseline(): void
    {
        $client = static::createClient();

        $client->request('GET', '/pastes/'.Base64Url::encode(random_bytes(16)).'/meta');

        self::assertResponseStatusCodeSame(404);
        foreach (SecurityHeadersSubscriber::BASELINE as $name => $value) {
            self::assertSame($value, $client->getResponse()->headers->get($name), $name);
        }
    }

    public function test_pages_keep_their_stricter_policy_and_also_get_the_rest_of_the_baseline(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(200);
        $headers = $client->getResponse()->headers;
        self::assertSame(ContentSecurityPolicy::PAGE, $headers->get('Content-Security-Policy'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
        self::assertSame('same-origin', $headers->get('Cross-Origin-Opener-Policy'));
    }

    public function test_the_policies_have_no_escape_hatches(): void
    {
        foreach ([ContentSecurityPolicy::PAGE, ContentSecurityPolicy::API] as $policy) {
            foreach (["'unsafe-inline'", "'unsafe-eval'", ' *', 'data:', 'blob:', 'http:', 'https:'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $policy);
            }
            self::assertStringContainsString("default-src 'none'", $policy);
            self::assertStringContainsString("frame-ancestors 'none'", $policy);
        }
        self::assertStringContainsString("script-src 'self'", ContentSecurityPolicy::PAGE);
    }
}