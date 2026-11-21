<?php

declare(strict_types=1);

namespace App\Tests\Paste\UI;

use App\Paste\Infrastructure\Persistence\Doctrine\DoctrinePasteRepository;
use App\Shared\Domain\Base64Url;
use App\Tests\Paste\Support\PasteFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PastePasswordApiTest extends WebTestCase
{
    public function test_kdf_parameters_are_returned_on_meta_and_read(): void
    {
        $client = static::createClient();
        $salt = Base64Url::encode(random_bytes(16));
        $created = $this->create($client, ['kdf' => ['salt' => $salt, 'iterations' => 600_000]]);
        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/pastes/'.$created['id'].'/meta');
        self::assertResponseStatusCodeSame(200);
        $meta = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(['hasPassword', 'kdf'], $this->sorted(array_keys($meta)));
        self::assertTrue($meta['hasPassword']);
        self::assertSame(['iterations', 'salt'], $this->sorted(array_keys($meta['kdf'])));
        self::assertSame($salt, $meta['kdf']['salt']);

        $client->request('GET', '/pastes/'.$created['id']);
        self::assertSame(['iterations' => 600_000, 'salt' => $salt], $this->sorted2(json_decode($client->getResponse()->getContent(), true)['kdf']));
    }

    public function test_a_paste_without_password_says_so(): void
    {
        $client = static::createClient();
        $created = $this->create($client);

        $client->request('GET', '/pastes/'.$created['id'].'/meta');
        self::assertSame(['hasPassword' => false, 'kdf' => null], json_decode($client->getResponse()->getContent(), true));

        $client->request('GET', '/pastes/'.$created['id']);
        self::assertNull(json_decode($client->getResponse()->getContent(), true)['kdf']);
    }

    public function test_meta_never_consumes_a_read(): void
    {
        $client = static::createClient();
        $created = $this->create($client, [
            'burnAfterRead' => true,
            'kdf' => ['salt' => Base64Url::encode(random_bytes(16)), 'iterations' => 600_000],
        ]);
        $url = '/pastes/'.$created['id'];

        foreach (range(1, 3) as $_) {
            $client->request('GET', $url.'/meta');
            self::assertResponseStatusCodeSame(200);
        }

        $client->request('GET', $url);
        self::assertResponseStatusCodeSame(200);
        $client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', $url.'/meta');
        self::assertResponseStatusCodeSame(404);
    }

    public function test_meta_of_unknown_or_expired_pastes_is_a_404_and_does_not_delete(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pastes/'.Base64Url::encode(random_bytes(16)).'/meta');
        self::assertResponseStatusCodeSame(404);

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $expired = PasteFactory::expired();
        (new DoctrinePasteRepository($em))->save($expired);
        $em->clear();

        $client->request('GET', '/pastes/'.$expired->id()->value.'/meta');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM paste WHERE id = ?', [$expired->id()->value]));
    }

    public function test_the_server_enforces_the_iteration_range_and_salt_format(): void
    {
        $client = static::createClient();
        $salt = Base64Url::encode(random_bytes(16));

        foreach ([
            ['salt' => $salt, 'iterations' => 599_999],
            ['salt' => $salt, 'iterations' => 2_000_001],
            ['salt' => Base64Url::encode(random_bytes(8)), 'iterations' => 600_000],
            ['salt' => $salt],
            ['iterations' => 600_000],
            'no-es-un-objeto',
        ] as $kdf) {
            $this->create($client, ['kdf' => $kdf]);
            self::assertResponseStatusCodeSame(422, json_encode($kdf));
        }

        foreach ([600_000, 2_000_000] as $iterations) {
            $this->create($client, ['kdf' => ['salt' => $salt, 'iterations' => $iterations]]);
            self::assertResponseStatusCodeSame(201);
        }
    }

    public function test_a_password_sent_by_mistake_is_not_stored(): void
    {
        $client = static::createClient();
        $created = $this->create($client, ['password' => 'mi-contraseña-secreta', 'secret' => 'otra-cosa']);
        self::assertResponseStatusCodeSame(201);

        $columns = array_map(
            static fn ($column): string => $column->getName(),
            array_values($this->connection()->createSchemaManager()->listTableColumns('paste')),
        );
        sort($columns);

        self::assertSame(
            ['burn_after_read', 'ciphertext', 'created_at', 'delete_token_hash', 'expires_at', 'id', 'iv', 'kdf_iterations', 'kdf_salt', 'max_views', 'owner_id', 'views'],
            $columns,
        );
        $row = $this->connection()->fetchAssociative('SELECT * FROM paste WHERE id = ?', [$created['id']]);
        self::assertStringNotContainsString('mi-contraseña-secreta', json_encode($row));
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

        return json_decode($client->getResponse()->getContent(), true) ?? [];
    }

    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    private function sorted2(array $values): array
    {
        ksort($values);

        return $values;
    }

    private function connection(): Connection
    {
        return static::getContainer()->get('doctrine.dbal.default_connection');
    }
}