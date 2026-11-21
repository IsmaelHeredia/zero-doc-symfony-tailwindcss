<?php

declare(strict_types=1);

namespace App\Tests\Organization\UI;

use App\Shared\Domain\Base64Url;
use App\Tests\Support\SeedsRoles;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TagApiTest extends WebTestCase
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

    public function test_tags_require_a_session(): void
    {
        $this->client->request('GET', '/tags');
        self::assertResponseStatusCodeSame(401);

        $this->json('POST', '/tags', ['name' => 'trabajo']);
        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_user_without_the_permission_gets_403(): void
    {
        $email = $this->registerUser();
        $this->runConsole($this->client->getKernel(), 'app:identity:revoke-role', ['email' => $email, 'role' => 'member']);
        $this->login($email);

        $this->json('POST', '/tags', ['name' => 'trabajo']);

        self::assertResponseStatusCodeSame(403);
    }

    public function test_tag_lifecycle_with_pivot_and_sql_join(): void
    {
        $this->login($this->registerUser());
        $tagId = $this->createTag('Trabajo')['id'];
        $pasteId = $this->createPaste()['id'];

        $this->client->request('PUT', "/tags/$tagId/pastes/$pasteId", server: self::AJAX);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('PUT', "/tags/$tagId/pastes/$pasteId", server: self::AJAX);
        self::assertResponseStatusCodeSame(204);

        $tags = $this->get('/tags')['tags'];
        self::assertSame([['id' => $tagId, 'name' => 'trabajo', 'pasteCount' => 1]], $tags);

        $listed = $this->get("/tags/$tagId/pastes")['pastes'];
        self::assertCount(1, $listed);
        self::assertSame($pasteId, $listed[0]['id']);
        self::assertSame(['createdAt', 'expiresAt', 'id', 'views'], $this->sorted(array_keys($listed[0])));
        self::assertStringNotContainsString('ciphertext', $this->client->getResponse()->getContent());

        $this->client->request('DELETE', "/tags/$tagId/pastes/$pasteId", server: self::AJAX);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->get('/tags')['tags'][0]['pasteCount']);
    }

    public function test_only_own_pastes_can_be_tagged(): void
    {
        $anonymousPaste = $this->createPaste()['id'];

        $ana = $this->registerUser();
        $this->login($ana);
        $anasPaste = $this->createPaste()['id'];
        $this->logout();

        $this->login($this->registerUser());
        $tagId = $this->createTag('mio')['id'];

        foreach ([$anonymousPaste, $anasPaste, Base64Url::encode(random_bytes(16))] as $notMine) {
            $this->client->request('PUT', "/tags/$tagId/pastes/$notMine", server: self::AJAX);
            self::assertResponseStatusCodeSame(404);
        }
        self::assertSame(0, $this->get('/tags')['tags'][0]['pasteCount']);
    }

    public function test_tags_of_other_users_are_invisible(): void
    {
        $this->login($this->registerUser());
        $tagId = $this->createTag('privado')['id'];
        $this->logout();

        $this->login($this->registerUser());
        self::assertSame([], $this->get('/tags')['tags']);

        $this->client->request('GET', "/tags/$tagId/pastes", server: self::AJAX);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('DELETE', "/tags/$tagId", server: self::AJAX);
        self::assertResponseStatusCodeSame(404);
    }

    public function test_destroying_a_paste_removes_it_from_its_tags(): void
    {
        $this->login($this->registerUser());
        $tagId = $this->createTag('temporal')['id'];
        $pasteId = $this->createPaste()['id'];
        $this->client->request('PUT', "/tags/$tagId/pastes/$pasteId", server: self::AJAX);
        self::assertSame(1, $this->get('/tags')['tags'][0]['pasteCount']);

        $this->client->request('DELETE', "/pastes/$pasteId", server: self::AJAX); 

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->get('/tags')['tags'][0]['pasteCount']);
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM paste_tag WHERE paste_id = ?', [$pasteId]));
    }

    public function test_burning_a_paste_removes_it_from_its_tags(): void
    {
        $this->login($this->registerUser());
        $tagId = $this->createTag('efimero')['id'];
        $pasteId = $this->createPaste(['burnAfterRead' => true])['id'];
        $this->client->request('PUT', "/tags/$tagId/pastes/$pasteId", server: self::AJAX);

        $this->client->request('GET', "/pastes/$pasteId");

        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM paste_tag WHERE paste_id = ?', [$pasteId]));
    }

    public function test_deleting_a_tag_keeps_the_pastes(): void
    {
        $this->login($this->registerUser());
        $tagId = $this->createTag('borrame')['id'];
        $pasteId = $this->createPaste()['id'];
        $this->client->request('PUT', "/tags/$tagId/pastes/$pasteId", server: self::AJAX);

        $this->client->request('DELETE', "/tags/$tagId", server: self::AJAX);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->get('/tags')['tags']);
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM paste WHERE id = ?', [$pasteId]));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM paste_tag WHERE tag_id = ?', [$tagId]));
    }

    public function test_duplicate_and_invalid_names(): void
    {
        $this->login($this->registerUser());
        $this->createTag('trabajo');

        $this->json('POST', '/tags', ['name' => 'TRABAJO']);
        self::assertResponseStatusCodeSame(409);

        $this->json('POST', '/tags', ['name' => '<b>x</b>']);
        self::assertResponseStatusCodeSame(422);

        $this->json('POST', '/tags', ['otra' => 'cosa']);
        self::assertResponseStatusCodeSame(422);
    }

    public function test_mutations_without_the_csrf_header_are_rejected(): void
    {
        $this->login($this->registerUser());

        $this->client->request('POST', '/tags', server: ['CONTENT_TYPE' => 'application/json'], content: '{"name":"x"}');

        self::assertResponseStatusCodeSame(403);
        self::assertSame('csrf', json_decode($this->client->getResponse()->getContent(), true)['error']);
    }

    private function registerUser(): string
    {
        $email = sprintf('user-%s@example.test', bin2hex(random_bytes(4)));
        $this->json('POST', '/auth/register', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(201);

        return $email;
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

    private function createTag(string $name): array
    {
        $this->json('POST', '/tags', ['name' => $name]);
        self::assertResponseStatusCodeSame(201);

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

    private function get(string $uri): array
    {
        $this->client->request('GET', $uri, server: self::AJAX);
        self::assertResponseStatusCodeSame(200);

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