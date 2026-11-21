<?php

declare(strict_types=1);

namespace App\Tests\Paste\UI;

use App\Shared\Domain\Base64Url;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PasteApiTest extends WebTestCase
{
    public function test_burn_after_read_flow(): void
    {
        $client = static::createClient();
        [$created, $sent] = $this->create($client, ['burnAfterRead' => true]);
        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/pastes/'.$created['id']);
        self::assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($sent['ciphertext'], $payload['ciphertext']);
        self::assertSame($sent['iv'], $payload['iv']);

        $client->request('GET', '/pastes/'.$created['id']);
        self::assertResponseStatusCodeSame(404);
    }

    public function test_max_views_allows_exactly_n_reads(): void
    {
        $client = static::createClient();
        [$created] = $this->create($client, ['maxViews' => 2]);

        foreach ([200, 200, 404] as $expected) {
            $client->request('GET', '/pastes/'.$created['id']);
            self::assertResponseStatusCodeSame($expected);
        }
    }

    public function test_delete_requires_the_right_token(): void
    {
        $client = static::createClient();
        [$created] = $this->create($client);
        $url = '/pastes/'.$created['id'];

        $client->request('DELETE', $url);
        self::assertResponseStatusCodeSame(403);

        $client->request('DELETE', $url, server: ['HTTP_X_DELETE_TOKEN' => Base64Url::encode(random_bytes(32))]);
        self::assertResponseStatusCodeSame(403);

        $client->request('DELETE', $url, server: ['HTTP_X_DELETE_TOKEN' => $created['deleteToken']]);
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
    }

    public function test_anonymous_cannot_create_permanent_or_over_cap(): void
    {
        $client = static::createClient();

        $this->create($client, ['ttl' => null]);
        self::assertResponseStatusCodeSame(422);

        $this->create($client, ['ttl' => 8 * 86400]);
        self::assertResponseStatusCodeSame(422);
    }

    public function test_rejects_malformed_payloads(): void
    {
        $client = static::createClient();

        $this->create($client, ['ciphertext' => 'no es base64url!!']);
        self::assertResponseStatusCodeSame(422);

        $this->create($client, ['iv' => Base64Url::encode(random_bytes(8))]);
        self::assertResponseStatusCodeSame(422);
    }

    private function create(KernelBrowser $client, array $override = []): array
    {
        $body = array_merge([
            'ciphertext' => Base64Url::encode(random_bytes(64)),
            'iv' => Base64Url::encode(random_bytes(12)),
            'ttl' => 3600,
            'burnAfterRead' => false,
            'maxViews' => null,
        ], $override);

        $client->request('POST', '/pastes', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body, JSON_THROW_ON_ERROR));

        return [json_decode($client->getResponse()->getContent(), true), $body];
    }
}