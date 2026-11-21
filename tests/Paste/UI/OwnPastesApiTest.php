<?php

declare(strict_types=1);

namespace App\Tests\Paste\UI;

use App\Shared\Domain\Base64Url;
use App\Tests\Support\SeedsRoles;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OwnPastesApiTest extends WebTestCase
{
    use SeedsRoles;

    private const PASSWORD = 'correct horse battery';
    private const AJAX = ['HTTP_X_REQUESTED_WITH' => 'fetch'];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->seedRoles($this->client->getKernel());
    }

    public function test_listing_requires_a_session(): void
    {
        $this->client->request('GET', '/pastes/mine');

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_user_without_list_permission_gets_403(): void
    {
        $email = $this->registerUser();
        $this->runConsole($this->client->getKernel(), 'app:identity:revoke-role', ['email' => $email, 'role' => 'member']);
        $this->login($email);

        $this->client->request('GET', '/pastes/mine', server: self::AJAX);

        self::assertResponseStatusCodeSame(403);
    }

    public function test_it_lists_only_my_pastes_with_metadata_and_nothing_else(): void
    {
        $this->createPaste();

        $owner = $this->registerUser();
        $this->login($owner);
        $mine = $this->createPaste(['maxViews' => 3, 'kdf' => ['salt' => Base64Url::encode(random_bytes(16)), 'iterations' => 600_000]])['id'];
        $this->logout();

        $this->login($this->registerUser());
        self::assertSame([], $this->mine()['pastes']);
        $this->logout();

        $this->login($owner);
        $data = $this->mine();
        
        self::assertFalse($data['truncated']);
        self::assertCount(1, $data['pastes']);

        $paste = $data['pastes'][0];
        self::assertSame($mine, $paste['id']);
        self::assertSame(['burnAfterRead', 'createdAt', 'expiresAt', 'hasPassword', 'id', 'maxViews', 'views'], $this->sorted(array_keys($paste)));
        self::assertTrue($paste['hasPassword']);
        self::assertSame(3, $paste['maxViews']);
        self::assertFalse($paste['burnAfterRead']);

        $raw = $this->client->getResponse()->getContent();
        foreach (['ciphertext', '"iv"', 'deleteToken', 'owner', 'salt', 'kdf'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $raw);
        }
    }

    public function test_expired_and_destroyed_pastes_disappear(): void
    {
        $this->login($this->registerUser());
        $alive = $this->createPaste()['id'];
        $expired = $this->createPaste()['id'];
        $deleted = $this->createPaste()['id'];

        $this->connection()->executeStatement('UPDATE paste SET expires_at = ? WHERE id = ?', ['2020-01-01 00:00:00', $expired]);
        $this->client->request('DELETE', "/pastes/$deleted", server: self::AJAX);
        self::assertResponseStatusCodeSame(204);

        $ids = array_column($this->mine()['pastes'], 'id');

        self::assertSame([$alive], $ids);
    }

    private string $lastEmail = '';

    private function registerUser(): string
    {
        $this->lastEmail = sprintf('user-%s@example.test', bin2hex(random_bytes(4)));
        $this->json('POST', '/auth/register', ['email' => $this->lastEmail, 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(201);

        return $this->lastEmail;
    }

    private function login(string $email): void
    {
        $this->json('POST', '/auth/login', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(200);
    }

    private function logout(): void
    {
        $this->client->request('POST', '/auth/logout');
        self::assertResponseStatusCodeSame(204);
    }

    private function mine(): array
    {
        $this->client->request('GET', '/pastes/mine', server: self::AJAX);
        self::assertResponseStatusCodeSame(200);

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function createPaste(array $override = []): array
    {
        $this->json('POST', '/pastes', array_merge([
            'ciphertext' => Base64Url::encode(random_bytes(64)),
            'iv' => Base64Url::encode(random_bytes(12)),
            'ttl' => 3600,
            'burnAfterRead' => false,
            'maxViews' => null,
        ], $override));
        self::assertResponseStatusCodeSame(201);

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function json(string $method, string $uri, array $body): void
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'] + self::AJAX, content: json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    private function connection(): Connection
    {
        return static::getContainer()->get('doctrine.dbal.default_connection');
    }
}