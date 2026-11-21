<?php

declare(strict_types=1);

namespace App\Tests\Paste\Infrastructure;

use App\Paste\Domain\Paste;
use App\Paste\Infrastructure\Persistence\Doctrine\DoctrinePasteRepository;
use App\Shared\Domain\UserId;
use App\Tests\Paste\Support\PasteFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeExpiredPastesTest extends KernelTestCase
{
    public function test_it_purges_only_expired_pastes_and_audits_each_one(): void
    {
        self::bootKernel();

        $this->connection()->executeStatement('DELETE FROM paste_deletion_log');
        $this->connection()->executeStatement('DELETE FROM paste WHERE expires_at IS NOT NULL AND expires_at <= NOW()');

        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $repository = new DoctrinePasteRepository($em);

        $expiredA = PasteFactory::expired();
        $expiredB = PasteFactory::expired();
        $alive = PasteFactory::alive();
        $permanent = PasteFactory::owned(UserId::generate());
        foreach ([$expiredA, $expiredB, $alive, $permanent] as $paste) {
            $repository->save($paste);
        }
        $em->clear();

        $tester = new CommandTester((new Application(self::$kernel))->find('app:paste:purge-expired'));
        $tester->execute(['--batch' => '1']);
        $tester->assertCommandIsSuccessful();

        foreach ([$expiredA, $expiredB] as $paste) {
            self::assertSame(0, $this->countRows('paste', $paste));
            $row = $this->connection()->fetchAssociative('SELECT * FROM paste_deletion_log WHERE paste_id = ?', [$paste->id()->value]);
            self::assertSame('expired', $row['reason']);
            self::assertNull($row['deleted_by_user_id']);
        }
        foreach ([$alive, $permanent] as $paste) {
            self::assertSame(1, $this->countRows('paste', $paste));
            self::assertSame(0, $this->countRows('paste_deletion_log', $paste, 'paste_id'));
        }

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        self::assertSame(1, $this->countRows('paste_deletion_log', $expiredA, 'paste_id'));
    }

    public function test_the_audit_table_cannot_hold_content(): void
    {
        self::bootKernel();

        $columns = array_map(
            static fn ($column): string => $column->getName(),
            array_values($this->connection()->createSchemaManager()->listTableColumns('paste_deletion_log')),
        );
        sort($columns);

        self::assertSame(['deleted_at', 'deleted_by_user_id', 'id', 'paste_id', 'reason'], $columns);
    }

    private function countRows(string $table, Paste $paste, string $column = 'id'): int
    {
        return (int) $this->connection()->fetchOne(sprintf('SELECT COUNT(*) FROM %s WHERE %s = ?', $table, $column), [$paste->id()->value]);
    }

    private function connection(): Connection
    {
        return self::getContainer()->get('doctrine.dbal.default_connection');
    }
}