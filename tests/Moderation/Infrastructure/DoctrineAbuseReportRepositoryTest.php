<?php

declare(strict_types=1);

namespace App\Tests\Moderation\Infrastructure;

use App\Moderation\Domain\AbuseReport;
use App\Moderation\Domain\Exception\ReportAlreadyFiled;
use App\Moderation\Domain\ValueObject\ReportComment;
use App\Moderation\Domain\ValueObject\ReportId;
use App\Moderation\Domain\ValueObject\ReportReason;
use App\Moderation\Domain\ValueObject\ReportStatus;
use App\Moderation\Infrastructure\Persistence\Doctrine\DoctrineAbuseReportRepository;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineAbuseReportRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DoctrineAbuseReportRepository $reports;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->reports = new DoctrineAbuseReportRepository($this->em);
        $this->now = new \DateTimeImmutable('2026-10-13 12:00:00');
    }

    public function test_a_report_roundtrips_with_enums_and_optional_fields(): void
    {
        $paste = PasteId::generate();
        $withComment = AbuseReport::file(ReportId::generate(), $paste, ReportReason::Malware, ReportComment::fromString('phishing'), UserId::generate(), $this->now);
        $bare = AbuseReport::file(ReportId::generate(), $paste, ReportReason::Other, null, null, $this->now);
        $this->reports->save($withComment);
        $this->reports->save($bare);
        $this->em->clear();

        $loaded = $this->em->wrapInTransaction(fn () => $this->reports->getForUpdate($withComment->id()));
        self::assertSame(ReportReason::Malware, $loaded->reason());
        self::assertSame(ReportStatus::Open, $loaded->status());
        self::assertSame('phishing', $loaded->comment()->value);

        $loadedBare = $this->em->wrapInTransaction(fn () => $this->reports->getForUpdate($bare->id()));
        self::assertNull($loadedBare->comment());
    }

    public function test_closed_reports_are_not_listed_as_open_and_not_counted(): void
    {
        $paste = PasteId::generate();
        $open = AbuseReport::file(ReportId::generate(), $paste, ReportReason::Spam, null, null, $this->now);
        $closed = AbuseReport::file(ReportId::generate(), $paste, ReportReason::Spam, null, null, $this->now);
        $closed->resolveBecausePasteDestroyed($this->now);
        $this->reports->save($open);
        $this->reports->save($closed);
        $this->em->clear();

        $found = $this->em->wrapInTransaction(fn () => $this->reports->findOpenByPaste($paste));

        self::assertCount(1, $found);
        self::assertTrue($found[0]->id()->equals($open->id()));
        self::assertSame(1, $this->reports->countOpenForPaste($paste));
    }

    public function test_the_same_user_cannot_report_the_same_paste_twice_at_database_level(): void
    {
        $paste = PasteId::generate();
        $user = UserId::generate();
        $this->reports->save(AbuseReport::file(ReportId::generate(), $paste, ReportReason::Spam, null, $user, $this->now));
        self::assertTrue($this->reports->hasReportFrom($paste, $user));
        self::assertFalse($this->reports->hasReportFrom($paste, UserId::generate()));

        $this->expectException(ReportAlreadyFiled::class);
        $this->reports->save(AbuseReport::file(ReportId::generate(), $paste, ReportReason::Other, null, $user, $this->now));
    }

    public function test_anonymous_reports_do_not_collide(): void
    {
        $paste = PasteId::generate();

        $this->reports->save(AbuseReport::file(ReportId::generate(), $paste, ReportReason::Spam, null, null, $this->now));
        $this->reports->save(AbuseReport::file(ReportId::generate(), $paste, ReportReason::Spam, null, null, $this->now));

        self::assertSame(2, $this->reports->countOpenForPaste($paste));
    }
}