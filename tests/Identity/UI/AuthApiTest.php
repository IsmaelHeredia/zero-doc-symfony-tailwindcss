<?php

declare(strict_types=1);

namespace App\Tests\Identity\UI;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use App\Tests\Support\SeedsRoles;

final class AuthApiTest extends WebTestCase
{
    use SeedsRoles;

    private const PASSWORD = 'correct horse battery';

    private function client(): KernelBrowser
    {
        $client = static::createClient();
        $this->seedRoles($client->getKernel());

        return $client;
    }

    public function test_register_login_me_logout_flow(): void
    {
        $client = static::createClient();
        $this->seedRoles($client->getKernel());
        $email = $this->randomEmail();

        $this->post($client, '/auth/register', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/auth/me');
        self::assertResponseStatusCodeSame(401);

        $this->post($client, '/auth/login', ['email' => strtoupper($email), 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(200);

        $client->request('GET', '/auth/me');
        self::assertResponseStatusCodeSame(200);
        $me = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($email, $me['email']);
        self::assertSame(['member'], $me['roles']);
        self::assertContains('paste.create', $me['permissions']);
        self::assertNotContains('paste.delete_any', $me['permissions']); 
        self::assertNotContains('user.manage', $me['permissions']);

        $client->request('POST', '/auth/logout');
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function test_login_fails_with_wrong_password_or_unknown_user(): void
    {
        $client = $this->client();
        $email = $this->randomEmail();
        $this->post($client, '/auth/register', ['email' => $email, 'password' => self::PASSWORD]);

        $this->post($client, '/auth/login', ['email' => $email, 'password' => 'wrong password here']);
        self::assertResponseStatusCodeSame(401);

        $this->post($client, '/auth/login', ['email' => $this->randomEmail(), 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(401);
    }

    public function test_duplicate_email_is_a_conflict(): void
    {
        $client = $this->client();
        $email = $this->randomEmail();

        $this->post($client, '/auth/register', ['email' => $email, 'password' => self::PASSWORD]);
        $this->post($client, '/auth/register', ['email' => strtoupper($email), 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(409);
    }

    public function test_invalid_registration_data_is_rejected(): void
    {
        $client = $this->client();

        $this->post($client, '/auth/register', ['email' => 'no-es-un-email', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(422);

        $this->post($client, '/auth/register', ['email' => $this->randomEmail(), 'password' => 'corta']);
        self::assertResponseStatusCodeSame(422);

        $this->post($client, '/auth/register', ['email' => $this->randomEmail()]);
        self::assertResponseStatusCodeSame(422);
    }

    public function test_the_password_is_stored_hashed(): void
    {
        $client = $this->client();
        $email = $this->randomEmail();
        $this->post($client, '/auth/register', ['email' => $email, 'password' => self::PASSWORD]);

        $stored = static::getContainer()->get('doctrine.dbal.default_connection')
            ->fetchOne('SELECT password_hash FROM user WHERE email = ?', [$email]);

        self::assertIsString($stored);
        self::assertStringNotContainsString(self::PASSWORD, $stored);
        self::assertTrue(password_verify(self::PASSWORD, $stored));
    }

    private function post(KernelBrowser $client, string $uri, array $body): void
    {
        $client->request('POST', $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function randomEmail(): string
    {
        return sprintf('user-%s@example.test', bin2hex(random_bytes(4)));
    }
}