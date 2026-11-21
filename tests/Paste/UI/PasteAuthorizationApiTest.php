<?php

declare(strict_types=1);

namespace App\Tests\Paste\UI;

use App\Shared\Domain\Base64Url;
use App\Tests\Support\SeedsRoles;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PasteAuthorizationApiTest extends WebTestCase
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

    public function test_an_authenticated_paste_is_owned_and_has_no_delete_token(): void
    {
        $this->login($this->registerUser());

        $created = $this->createPaste(['ttl' => null]); 

        self::assertResponseStatusCodeSame(201);
        self::assertNull($created['deleteToken']);
    }

    public function test_large_pastes_are_for_users_with_paste_large(): void
    {
        $big = Base64Url::encode(random_bytes(600 * 1024)); 

        $this->createPaste(['ciphertext' => $big]);
        self::assertResponseStatusCodeSame(422);

        $this->login($this->registerUser());
        $this->createPaste(['ciphertext' => $big]);
        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_user_without_paste_create_cannot_create(): void
    {
        $email = $this->registerUser();
        $this->console('app:identity:revoke-role', ['email' => $email, 'role' => 'member']);
        $this->login($email);

        $this->createPaste();

        self::assertResponseStatusCodeSame(403);
    }

    public function test_only_the_owner_can_delete_an_owned_paste(): void
    {
        $owner = $this->registerUser();
        $stranger = $this->registerUser();

        $this->login($owner);
        $id = $this->createPaste()['id'];
        $this->logout();

        $this->login($stranger);
        $this->delete($id);
        self::assertResponseStatusCodeSame(403);
        $this->logout();

        $this->login($owner);
        $this->delete($id);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/pastes/' . $id);
        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_moderator_deletes_by_id_without_token_but_a_member_cannot(): void
    {
        $anonymousId = $this->createPaste()['id'];

        $this->login($this->registerUser());
        $this->delete($anonymousId);
        self::assertResponseStatusCodeSame(403);
        $this->logout();

        $this->login($this->registerUser('moderator'));
        $this->delete($anonymousId);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/pastes/' . $anonymousId);
        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_moderator_can_delete_an_owned_paste_too(): void
    {
        $owner = $this->registerUser();
        $this->login($owner);
        $id = $this->createPaste()['id'];
        $this->logout();

        $this->login($this->registerUser('moderator'));
        $this->delete($id);

        self::assertResponseStatusCodeSame(204);
    }

    public function test_session_requests_without_the_csrf_header_are_rejected(): void
    {
        $this->login($this->registerUser());
        $id = $this->createPaste()['id'];

        $this->client->request('DELETE', '/pastes/' . $id);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('csrf', json_decode($this->client->getResponse()->getContent(), true)['error']);

        $this->delete($id);
        self::assertResponseStatusCodeSame(204);
    }

    private function registerUser(string ...$extraRoles): string
    {
        $email = sprintf('user-%s@example.test', bin2hex(random_bytes(4)));
        $this->json('POST', '/auth/register', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(201);

        foreach ($extraRoles as $role) {
            $this->console('app:identity:assign-role', ['email' => $email, 'role' => $role]);
        }

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

    private function createPaste(array $override = []): array
    {
        $this->json('POST', '/pastes', array_merge([
            'ciphertext' => Base64Url::encode(random_bytes(64)),
            'iv' => Base64Url::encode(random_bytes(12)),
            'ttl' => 3600,
            'burnAfterRead' => false,
            'maxViews' => null,
        ], $override));

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function delete(string $id): void
    {
        $this->client->request('DELETE', '/pastes/' . $id, server: self::AJAX);
    }

    private function json(string $method, string $uri, array $body): void
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'] + self::AJAX, content: json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function console(string $command, array $arguments): void
    {
        $this->runConsole($this->client->getKernel(), $command, $arguments);
    }

    public function test_owner_and_moderator_deletions_leave_an_audit_trail(): void
    {
        $this->login($this->registerUser());
        $ownerId = $this->currentUserId();
        $byOwner = $this->createPaste()['id'];
        $forModerator = $this->createPaste()['id'];
        $this->delete($byOwner);
        self::assertResponseStatusCodeSame(204);
        $this->logout();

        $this->login($this->registerUser('moderator'));
        $moderatorId = $this->currentUserId();
        $this->delete($forModerator);
        self::assertResponseStatusCodeSame(204);

        $ownerRow = $this->auditRow($byOwner);
        self::assertSame('owner', $ownerRow['reason']);
        self::assertSame($ownerId, $ownerRow['deleted_by_user_id']);

        $moderationRow = $this->auditRow($forModerator);
        self::assertSame('moderator', $moderationRow['reason']);
        self::assertSame($moderatorId, $moderationRow['deleted_by_user_id']);
    }

    private function currentUserId(): string
    {
        $this->client->request('GET', '/auth/me', server: self::AJAX);

        return json_decode($this->client->getResponse()->getContent(), true)['id'];
    }

    private function auditRow(string $pasteId): array
    {
        $row = static::getContainer()->get('doctrine.dbal.default_connection')
            ->fetchAssociative('SELECT * FROM paste_deletion_log WHERE paste_id = ?', [$pasteId]);
        self::assertIsArray($row);

        return $row;
    }

}