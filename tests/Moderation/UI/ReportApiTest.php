<?php

declare(strict_types=1);

namespace App\Tests\Moderation\UI;

use App\Shared\Domain\Base64Url;
use App\Tests\Support\SeedsRoles;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReportApiTest extends WebTestCase
{
    use SeedsRoles;

    private const PASSWORD = 'correct horse battery';
    private const AJAX = ['HTTP_X_REQUESTED_WITH' => 'fetch'];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->seedRoles($this->client->getKernel());
        $this->connection()->executeStatement('DELETE FROM abuse_report');
    }

    public function test_an_anonymous_user_can_report_without_touching_the_paste(): void
    {
        $paste = $this->createPaste(['burnAfterRead' => true])['id'];

        $this->fileReport($paste, 'spam', 'parece spam');

        self::assertResponseStatusCodeSame(202);
        self::assertSame(1, $this->reportCount($paste));

        $this->client->request('GET', '/pastes/'.$paste);
        self::assertResponseStatusCodeSame(200);
        $this->client->request('GET', '/pastes/'.$paste);
        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_nonexistent_paste_gets_the_same_answer_and_stores_nothing(): void
    {
        $unknown = Base64Url::encode(random_bytes(16));

        $this->fileReport($unknown, 'spam', null);

        self::assertResponseStatusCodeSame(202);
        self::assertSame(['status' => 'received'], json_decode($this->client->getResponse()->getContent(), true));
        self::assertSame(0, $this->reportCount($unknown));
    }

    public function test_invalid_payloads_are_rejected(): void
    {
        $paste = $this->createPaste()['id'];

        $this->fileReport($paste, 'no-es-un-motivo', null);
        self::assertResponseStatusCodeSame(422);

        $this->fileReport('corto', 'spam', null);
        self::assertResponseStatusCodeSame(422);

        $this->postReport(['reason' => 'spam']);
        self::assertResponseStatusCodeSame(422);

        $this->postReport(['pasteId' => $paste, 'reason' => 'spam', 'comment' => ['no' => 'string']]);
        self::assertResponseStatusCodeSame(422);

        self::assertSame(0, $this->reportCount($paste));
    }

    public function test_a_comment_that_carries_a_key_is_rejected_and_not_stored(): void
    {
        $paste = $this->createPaste()['id'];
        $key = Base64Url::encode(random_bytes(32));

        $this->fileReport($paste, 'illegal', "mirad http://localhost:8000/p/$paste#$key");

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->reportCount($paste));
        self::assertStringNotContainsString($key, $this->client->getResponse()->getContent());
    }

    public function test_the_rate_limit_is_per_client_and_answers_429(): void
    {
        $paste = $this->createPaste()['id'];
        $ip = $this->randomIp();

        for ($i = 0; $i < 5; ++$i) {
            $this->fileReport($paste, 'spam', null, $ip);
            self::assertResponseStatusCodeSame(202);
        }

        $this->fileReport($paste, 'spam', null, $ip);
        self::assertResponseStatusCodeSame(429);
        self::assertGreaterThan(0, (int) $this->client->getResponse()->headers->get('Retry-After'));

        $this->fileReport($paste, 'spam', null, $this->randomIp());
        self::assertResponseStatusCodeSame(202);
    }

    public function test_a_signed_in_user_cannot_pile_up_reports_on_the_same_paste(): void
    {
        $paste = $this->createPaste()['id'];
        $this->login($this->registerUser());

        $this->fileReport($paste, 'spam', null);
        $this->fileReport($paste, 'malware', 'otra vez');

        self::assertResponseStatusCodeSame(202);
        self::assertSame(1, $this->reportCount($paste));
    }

    public function test_signed_in_reporting_requires_the_csrf_header_but_anonymous_does_not(): void
    {
        $paste = $this->createPaste()['id'];
        $body = json_encode(['pasteId' => $paste, 'reason' => 'spam', 'comment' => null], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/reports', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $this->randomIp()], content: $body);
        self::assertResponseStatusCodeSame(202);

        $this->login($this->registerUser());
        $this->client->request('POST', '/reports', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $this->randomIp()], content: $body);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('csrf', json_decode($this->client->getResponse()->getContent(), true)['error']);
    }

    public function test_only_reviewers_can_see_or_dismiss_reports(): void
    {
        $paste = $this->createPaste()['id'];
        $this->fileReport($paste, 'spam', null);
        $reportId = $this->reportIds($paste)[0];

        $this->client->request('GET', '/moderation/reports');
        self::assertResponseStatusCodeSame(401);

        $this->login($this->registerUser());
        $this->client->request('GET', '/moderation/reports', server: self::AJAX);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', "/moderation/reports/$reportId/dismiss", server: self::AJAX);
        self::assertResponseStatusCodeSame(403);

        $this->logout();
        $this->login($this->registerUser('admin'));
        $this->client->request('GET', '/moderation/reports', server: self::AJAX);
        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_review_queue_exposes_metadata_only(): void
    {
        $paste = $this->createPaste()['id'];
        $this->fileReport($paste, 'harassment', 'comentario de prueba');
        $this->fileReport($paste, 'spam', null);
        $this->login($this->registerUser('moderator'));

        $reports = $this->get('/moderation/reports')['reports'];

        self::assertCount(2, $reports);
        self::assertSame(['comment', 'createdAt', 'id', 'openReportsForPaste', 'pasteId', 'reason'], $this->sorted(array_keys($reports[0])));
        self::assertSame(2, $reports[0]['openReportsForPaste']);
        $raw = $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('ciphertext', $raw);
        self::assertStringNotContainsString('reporter', $raw);
    }

    public function test_dismissing_closes_the_report_and_keeps_the_paste(): void
    {
        $paste = $this->createPaste()['id'];
        $this->fileReport($paste, 'spam', null);
        $reportId = $this->reportIds($paste)[0];
        $this->login($this->registerUser('moderator'));
        $moderatorId = $this->currentUserId();

        $this->client->request('POST', "/moderation/reports/$reportId/dismiss", server: self::AJAX);
        self::assertResponseStatusCodeSame(204);

        $row = $this->connection()->fetchAssociative('SELECT status, closed_by FROM abuse_report WHERE id = ?', [$reportId]);
        self::assertSame('dismissed', $row['status']);
        self::assertSame($moderatorId, $row['closed_by']);
        self::assertSame([], $this->get('/moderation/reports')['reports']);
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM paste WHERE id = ?', [$paste]));

        $this->client->request('POST', "/moderation/reports/$reportId/dismiss", server: self::AJAX);
        self::assertResponseStatusCodeSame(409);
    }

    public function test_dismissing_an_unknown_report_is_a_404(): void
    {
        $this->login($this->registerUser('moderator'));

        $this->client->request('POST', '/moderation/reports/11111111-1111-4111-8111-111111111111/dismiss', server: self::AJAX);

        self::assertResponseStatusCodeSame(404);
    }

    public function test_destroying_the_paste_resolves_its_reports(): void
    {
        $paste = $this->createPaste()['id'];
        $this->fileReport($paste, 'malware', 'phishing');
        $this->fileReport($paste, 'spam', null);
        $this->login($this->registerUser('moderator'));
        self::assertCount(2, $this->get('/moderation/reports')['reports']);

        $this->client->request('DELETE', "/pastes/$paste", server: self::AJAX);
        self::assertResponseStatusCodeSame(204);

        self::assertSame([], $this->get('/moderation/reports')['reports']);
        $rows = $this->connection()->fetchAllAssociative('SELECT status, closed_by FROM abuse_report WHERE paste_id = ?', [$paste]);
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertSame('resolved', $row['status']);
            self::assertNull($row['closed_by']);
        }
        self::assertSame('moderator', $this->connection()->fetchOne('SELECT reason FROM paste_deletion_log WHERE paste_id = ?', [$paste]));
    }

    public function test_burning_a_paste_also_resolves_its_reports(): void
    {
        $paste = $this->createPaste(['burnAfterRead' => true])['id'];
        $this->fileReport($paste, 'spam', null);

        $this->client->request('GET', '/pastes/'.$paste);

        self::assertSame('resolved', $this->connection()->fetchOne('SELECT status FROM abuse_report WHERE paste_id = ?', [$paste]));
    }

    public function test_the_report_table_cannot_hold_network_data_or_paste_content(): void
    {
        $columns = array_map(
            static fn ($column): string => $column->getName(),
            array_values($this->connection()->createSchemaManager()->listTableColumns('abuse_report')),
        );
        sort($columns);

        self::assertSame(
            ['closed_at', 'closed_by', 'comment_text', 'created_at', 'id', 'paste_id', 'reason', 'reporter_id', 'status'],
            $columns,
        );
    }

    private function fileReport(string $pasteId, string $reason, ?string $comment, ?string $ip = null): void
    {
        $this->postReport(['pasteId' => $pasteId, 'reason' => $reason, 'comment' => $comment], $ip);
    }

    private function postReport(array $body, ?string $ip = null): void
    {
        $this->client->request('POST', '/reports', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => $ip ?? $this->randomIp(),
        ] + self::AJAX, content: json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function randomIp(): string
    {
        return sprintf('10.%d.%d.%d', random_int(1, 254), random_int(1, 254), random_int(1, 254));
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

    private function currentUserId(): string
    {
        return $this->get('/auth/me')['id'];
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

    private function reportIds(string $pasteId): array
    {
        return array_map('strval', $this->connection()->fetchFirstColumn('SELECT id FROM abuse_report WHERE paste_id = ?', [$pasteId]));
    }

    private function reportCount(string $pasteId): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM abuse_report WHERE paste_id = ?', [$pasteId]);
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