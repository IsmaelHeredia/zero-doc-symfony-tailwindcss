<?php

declare(strict_types=1);

namespace App\Tests\Moderation\Domain;

use App\Moderation\Domain\AbuseReport;
use App\Moderation\Domain\Event\AbuseReported;
use App\Moderation\Domain\Event\ReportDismissed;
use App\Moderation\Domain\Event\ReportResolved;
use App\Moderation\Domain\Exception\ReportAlreadyClosed;
use App\Moderation\Domain\ValueObject\ReportComment;
use App\Moderation\Domain\ValueObject\ReportId;
use App\Moderation\Domain\ValueObject\ReportReason;
use App\Moderation\Domain\ValueObject\ReportStatus;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;
use PHPUnit\Framework\TestCase;

final class AbuseReportTest extends TestCase
{
    public function test_filing_records_an_event_without_comment_or_reporter(): void
    {
        $report = $this->report(ReportComment::fromString('texto libre'), UserId::generate());

        $events = $report->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AbuseReported::class, $events[0]);
        self::assertSame(ReportReason::Spam, $events[0]->reason);
        self::assertSame(['reportId', 'pasteId', 'reason', 'occurredAt'], array_keys(get_object_vars($events[0])));
        self::assertTrue($report->isOpen());
    }

    public function test_a_moderator_can_dismiss_an_open_report_once(): void
    {
        $report = $this->report();
        $report->pullDomainEvents();
        $reviewer = UserId::generate();

        $report->dismiss($reviewer, $this->now());

        self::assertSame(ReportStatus::Dismissed, $report->status());
        self::assertTrue($report->closedBy()->equals($reviewer));
        self::assertInstanceOf(ReportDismissed::class, $report->pullDomainEvents()[0]);

        $this->expectException(ReportAlreadyClosed::class);
        $report->dismiss($reviewer, $this->now());
    }

    public function test_destroying_the_paste_resolves_the_report_without_a_reviewer(): void
    {
        $report = $this->report();
        $report->pullDomainEvents();

        $report->resolveBecausePasteDestroyed($this->now());

        self::assertSame(ReportStatus::Resolved, $report->status());
        self::assertNull($report->closedBy());
        self::assertInstanceOf(ReportResolved::class, $report->pullDomainEvents()[0]);
    }

    public function test_resolving_is_idempotent_and_never_overrides_a_human_decision(): void
    {
        $report = $this->report();
        $report->dismiss(UserId::generate(), $this->now());
        $report->pullDomainEvents();

        $report->resolveBecausePasteDestroyed($this->now());

        self::assertSame(ReportStatus::Dismissed, $report->status());
        self::assertSame([], $report->pullDomainEvents());
    }

    public function test_a_resolved_report_cannot_be_dismissed(): void
    {
        $report = $this->report();
        $report->resolveBecausePasteDestroyed($this->now());

        $this->expectException(ReportAlreadyClosed::class);
        $report->dismiss(UserId::generate(), $this->now());
    }

    private function report(?ReportComment $comment = null, ?UserId $reporter = null): AbuseReport
    {
        return AbuseReport::file(ReportId::generate(), PasteId::generate(), ReportReason::Spam, $comment, $reporter, $this->now());
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-13 12:00:00');
    }
}