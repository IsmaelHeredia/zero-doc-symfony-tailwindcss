<?php

declare(strict_types=1);

namespace App\Tests\Identity\UI;

use App\Identity\Domain\Role;
use App\Identity\Domain\ValueObject\RoleId;
use App\Identity\Domain\ValueObject\RoleName;
use App\Identity\Infrastructure\Persistence\Doctrine\DoctrineRoleRepository;
use App\Tests\Support\SeedsRoles;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminApiTest extends WebTestCase
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

    public function test_access_depends_on_the_permissions(): void
    {
        $this->client->request('GET', '/admin/users');
        self::assertResponseStatusCodeSame(401);

        foreach (['member' => [], 'moderator' => ['moderator']] as $roles) {
            $this->login($this->registerUser(...$roles));
            foreach (['/admin/users', '/admin/roles'] as $uri) {
                $this->client->request('GET', $uri, server: self::AJAX);
                self::assertResponseStatusCodeSame(403);
            }
            $this->logout();
        }

        $this->login($this->registerUser('admin'));
        $this->client->request('GET', '/admin/users', server: self::AJAX);
        self::assertResponseStatusCodeSame(200);
        $this->client->request('GET', '/admin/roles', server: self::AJAX);
        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_user_list_has_metadata_only(): void
    {
        $this->login($this->registerUser('admin'));

        $data = $this->get('/admin/users');

        self::assertNotEmpty($data['users']);
        self::assertSame(['createdAt', 'email', 'id', 'roles'], $this->sorted(array_keys($data['users'][0])));
        self::assertContains('admin', $data['availableRoles']);
        $raw = $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('password', $raw);
        self::assertStringNotContainsString('$2y$', $raw);
        self::assertStringNotContainsString('argon', $raw);
    }

    public function test_assign_and_revoke_a_role_with_audit_trail(): void
    {
        $targetEmail = $this->registerUser();
        $targetId = $this->userId($targetEmail);
        $this->login($this->registerUser('admin'));
        $adminId = $this->get('/auth/me')['id'];

        $this->client->request('PUT', "/admin/users/$targetId/roles/moderator", server: self::AJAX);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('PUT', "/admin/users/$targetId/roles/moderator", server: self::AJAX);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->roleHolders($targetId, 'moderator'));

        $this->client->request('DELETE', "/admin/users/$targetId/roles/moderator", server: self::AJAX);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->roleHolders($targetId, 'moderator'));

        $rows = $this->connection()->fetchAllAssociative('SELECT * FROM admin_audit_log WHERE target_user_id = ? ORDER BY id', [$targetId]);
        self::assertSame(['role_assigned', 'role_revoked'], array_column($rows, 'action'));
        self::assertSame($adminId, $rows[0]['actor_id']);
        self::assertSame('moderator', $rows[0]['role_name']);
    }

    public function test_an_admin_can_demote_another_admin_when_one_remains(): void
    {
        $other = $this->userId($this->registerUser('admin'));
        $this->login($this->registerUser('admin'));

        $this->client->request('DELETE', "/admin/users/$other/roles/admin", server: self::AJAX);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->roleHolders($other, 'admin'));
    }

    public function test_unknown_targets_and_malformed_ids(): void
    {
        $this->login($this->registerUser('admin'));

        $this->client->request('PUT', '/admin/users/11111111-1111-4111-8111-111111111111/roles/moderator', server: self::AJAX);
        self::assertResponseStatusCodeSame(404);

        $target = $this->userId($this->registerUser());
        $this->client->request('PUT', "/admin/users/$target/roles/inexistente", server: self::AJAX);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('PUT', '/admin/users/no-es-uuid/roles/moderator', server: self::AJAX);
        self::assertResponseStatusCodeSame(404);
    }

    public function test_grant_and_revoke_permissions_on_a_role_with_audit_trail(): void
    {
        $role = 'r'.bin2hex(random_bytes(4));
        $repository = new DoctrineRoleRepository(static::getContainer()->get('doctrine.orm.entity_manager'));
        $repository->save(Role::create(RoleId::generate(), RoleName::fromString($role)));
        $this->login($this->registerUser('admin'));

        $this->client->request('PUT', "/admin/roles/$role/permissions/paste.large", server: self::AJAX);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->permissionHolders($role, 'paste.large'));

        $view = $this->get('/admin/roles');
        $names = array_column($view['roles'], 'permissions', 'name');
        self::assertSame(['paste.large'], $names[$role]);
        self::assertContains('paste.large', $view['catalog']);
        self::assertSame(['user.manage', 'role.manage'], $view['protectedAdminPermissions']);

        $this->client->request('DELETE', "/admin/roles/$role/permissions/paste.large", server: self::AJAX);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->permissionHolders($role, 'paste.large'));

        $actions = $this->connection()->fetchFirstColumn('SELECT action FROM admin_audit_log WHERE role_name = ? ORDER BY id', [$role]);
        self::assertSame(['permission_granted', 'permission_revoked'], $actions);
    }

    public function test_only_catalog_permissions_can_be_granted(): void
    {
        $this->login($this->registerUser('admin'));

        foreach (['paste.read_any', 'paste.view_content', 'inventado.cosa'] as $code) {
            $this->client->request('PUT', "/admin/roles/moderator/permissions/$code", server: self::AJAX);
            self::assertResponseStatusCodeSame(422, $code);
        }
        self::assertSame(0, $this->permissionHolders('moderator', 'paste.read_any'));
    }

    public function test_the_admin_role_cannot_lose_its_administration_permissions(): void
    {
        $this->login($this->registerUser('admin'));

        foreach (['user.manage', 'role.manage'] as $code) {
            $this->client->request('DELETE', "/admin/roles/admin/permissions/$code", server: self::AJAX);
            self::assertResponseStatusCodeSame(409);
            self::assertSame(1, $this->permissionHolders('admin', $code));
        }
        $this->client->request('GET', '/admin/roles', server: self::AJAX);
        self::assertResponseStatusCodeSame(200);
    }

    public function test_session_requests_without_the_csrf_header_are_rejected(): void
    {
        $target = $this->userId($this->registerUser());
        $this->login($this->registerUser('admin'));

        $this->client->request('PUT', "/admin/users/$target/roles/moderator");

        self::assertResponseStatusCodeSame(403);
        self::assertSame('csrf', json_decode($this->client->getResponse()->getContent(), true)['error']);
        self::assertSame(0, $this->roleHolders($target, 'moderator'));
    }

    public function test_the_admin_audit_table_columns_are_fixed(): void
    {
        $columns = array_map(
            static fn ($column): string => $column->getName(),
            array_values($this->connection()->createSchemaManager()->listTableColumns('admin_audit_log')),
        );
        sort($columns);

        self::assertSame(['action', 'actor_id', 'id', 'occurred_at', 'permission_code', 'role_name', 'target_user_id'], $columns);
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
        return (string) $this->connection()->fetchOne('SELECT id FROM user WHERE email = ?', [$email]);
    }

    private function roleHolders(string $userId, string $role): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM user_role ur JOIN role r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.name = ?',
            [$userId, $role],
        );
    }

    private function permissionHolders(string $role, string $code): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM role_permission rp JOIN role r ON r.id = rp.role_id JOIN permission p ON p.id = rp.permission_id WHERE r.name = ? AND p.code = ?',
            [$role, $code],
        );
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