<?php

declare(strict_types=1);

namespace App\Tests\Paste\UI;

use App\Paste\Infrastructure\Persistence\Doctrine\DoctrinePasteRepository;
use App\Shared\Domain\Base64Url;
use App\Tests\Paste\Support\PasteFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PasteDeletionAuditApiTest extends WebTestCase
{
    public function test_token_deletion_is_logged_without_actor(): void
    {
        $client = static::createClient();
        $created = $this->createPaste($client);

        $client->request('DELETE', '/pastes/' . $created['id'], server: ['HTTP_X_DELETE_TOKEN' => $created['deleteToken']]);
        self::assertResponseStatusCodeSame(204);

        $row = $this->logRow($created['id']);
        self::assertSame('token', $row['reason']);
        self::assertNull($row['deleted_by_user_id']);
    }

    public function test_burn_after_read_is_logged(): void
    {
        $client = static::createClient();
        $created = $this->createPaste($client, ['burnAfterRead' => true]);

        $client->request('GET', '/pastes/' . $created['id']);
        self::assertResponseStatusCodeSame(200);

        self::assertSame('burned', $this->logRow($created['id'])['reason']);
        self::assertSame(0, $this->pasteCount($created['id']));
    }

    public function test_lazy_expiry_on_read_is_logged(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $paste = PasteFactory::expired();
        (new DoctrinePasteRepository($em))->save($paste);
        $em->clear();

        $client->request('GET', '/pastes/' . $paste->id()->value);

        self::assertResponseStatusCodeSame(404);
        $row = $this->logRow($paste->id()->value);
        self::assertSame('expired', $row['reason']);
        self::assertNull($row['deleted_by_user_id']);
        self::assertSame(0, $this->pasteCount($paste->id()->value));
    }

    public function test_if_the_audit_cannot_be_written_the_deletion_is_rolled_back(): void
    {
        $client = static::createClient();
        $created = $this->createPaste($client);

        $this->connection()->insert('paste_deletion_log', [
            'paste_id' => $created['id'],
            'reason' => 'expired',
            'deleted_at' => '2020-01-01 00:00:00',
        ]);

        $client->request('DELETE', '/pastes/' . $created['id'], server: ['HTTP_X_DELETE_TOKEN' => $created['deleteToken']]);

        self::assertResponseStatusCodeSame(500);
        self::assertSame(1, $this->pasteCount($created['id']));

        $this->connection()->executeStatement('DELETE FROM paste_deletion_log WHERE paste_id = ?', [$created['id']]);
        $this->connection()->executeStatement('DELETE FROM paste WHERE id = ?', [$created['id']]);

    }

    private function createPaste(KernelBrowser $client, array $override = []): array
    {
        $body = array_merge([
            'ciphertext' => Base64Url::encode(random_bytes(64)),
            'iv' => Base64Url::encode(random_bytes(12)),
            'ttl' => 3600,
            'burnAfterRead' => false,
            'maxViews' => null,
        ], $override);
        $client->request('POST', '/pastes', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        return json_decode($client->getResponse()->getContent(), true);
    }

    private function logRow(string $pasteId): array
    {
        $row = $this->connection()->fetchAssociative('SELECT * FROM paste_deletion_log WHERE paste_id = ?', [$pasteId]);
        self::assertIsArray($row, 'No hay fila de auditoría para el paste.');

        return $row;
    }

    private function pasteCount(string $pasteId): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM paste WHERE id = ?', [$pasteId]);
    }

    private function connection(): Connection
    {
        return static::getContainer()->get('doctrine.dbal.default_connection');
    }
}