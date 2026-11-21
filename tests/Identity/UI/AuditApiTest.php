<?php

declare(strict_types=1);

namespace App\Tests\Identity\UI;

use App\Shared\Domain\Base64Url;
use App\Tests\Support\SeedsRoles;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuditApiTest extends WebTestCase
{
    use SeedsRoles;

    private const PASSWORD = 'correct horse battery';
    private const AJAX = ['HTTP_X_REQUESTED_WITH' => 'fetch'];
    private const AUDIT_URLS = ['/admin/audit/actions', '/admin/audit/deletions'];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->seedRoles($this->client->getKernel());
    }

    public function test_only_admin_reads_the_audit_log(): void
    {
        foreach (self::AUDIT_URLS as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(401);
        }

        foreach ([[], ['moderator']] as $roles) {
            $this->login($this->registerUser(...$roles));
            foreach (self::AUDIT_URLS as $url) {
                $this->client->request('GET', $url, server: self::AJAX);
                self::assertResponseStatusCodeSame(403);
            }
            $this->logout();
        }

        $this->login($this->registerUser('admin'));
        foreach (self::AUDIT_URLS as $url) {
            $this->client->request('GET', $url, server: self::AJAX);
            self::assertResponseStatusCodeSame(200);
        }
    }

    public function test_admin_actions_are_listed_newest_first_with_ids_only(): void
    {
        $targetId = $this->userId($this->registerUser());
        $adminEmail = $this->registerUser('admin');
        $this->login($adminEmail);
        $adminId = $this->get('/auth/me')['id'];

        $this->client->request('PUT', "/admin/users/$targetId/roles/moderator", server: self::AJAX);
        $this->client->request('DELETE', "/admin/users/$targetId/roles/moderator", server: self::AJAX);

        $actions = $this->get('/admin/audit/actions')['actions'];

        self::assertSame('role_revoked', $actions[0]['action']);
        self::assertSame('role_assigned', $actions[1]['action']);
        self::assertSame(['action', 'actorId', 'occurredAt', 'permissionCode', 'roleName', 'targetUserId'], $this->sorted(array_keys($actions[0])));
        self::assertSame($adminId, $actions[0]['actorId']);
        self::assertSame($targetId, $actions[0]['targetUserId']);
        self::assertSame('moderator', $actions[0]['roleName']);
        self::assertStringNotContainsString($adminEmail, $this->client->getResponse()->getContent());
    }

    public function test_deletions_show_reason_and_actor_but_never_content(): void
    {
        $byToken = $this->createPaste();
        $this->client->request('DELETE', '/pastes/'.$byToken['id'], server: ['HTTP_X_DELETE_TOKEN' => $byToken['deleteToken']]);
        self::assertResponseStatusCodeSame(204);

        $byModerator = $this->createPaste();
        $this->login($this->registerUser('moderator'));
        $moderatorId = $this->get('/auth/me')['id'];
        $this->client->request('DELETE', '/pastes/'.$byModerator['id'], server: self::AJAX);
        self::assertResponseStatusCodeSame(204);
        $this->logout();

        $this->login($this->registerUser('admin'));
        $deletions = array_column($this->get('/admin/audit/deletions')['deletions'], null, 'pasteId');

        self::assertSame('token', $deletions[$byToken['id']]['reason']);
        self::assertNull($deletions[$byToken['id']]['actorId']);
        self::assertSame('moderator', $deletions[$byModerator['id']]['reason']);
        self::assertSame($moderatorId, $deletions[$byModerator['id']]['actorId']);
        self::assertSame(['actorId', 'deletedAt', 'pasteId', 'reason'], $this->sorted(array_keys($deletions[$byToken['id']])));

        $raw = $this->client->getResponse()->getContent();
        foreach (['ciphertext', '"iv"', 'deleteToken', 'salt'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $raw);
        }
    }

    private function registerUser(string ...$extraRoles): string
    {
        $email = sprintf('user-%s@example.test', bin2hex(random_bytes(4)));
        $this->json('POST', '/auth/register', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(201);

        foreach ($extraRoles as $role) {
            $this->runConsole($this->client->getKernel(), 'app:identity:assign-role', ['email' => $email, 'role' => $role]);
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

    private function userId(string $email): string
    {
        return (string) static::getContainer()->get('doctrine.dbal.default_connection')
            ->fetchOne('SELECT id FROM user WHERE email = ?', [$email]);
    }

    private function createPaste(): array
    {
        $this->json('POST', '/pastes', [
            'ciphertext' => Base64Url::encode(random_bytes(64)),
            'iv' => Base64Url::encode(random_bytes(12)),
            'ttl' => 3600,
            'burnAfterRead' => false,
            'maxViews' => null,
        ]);
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
}