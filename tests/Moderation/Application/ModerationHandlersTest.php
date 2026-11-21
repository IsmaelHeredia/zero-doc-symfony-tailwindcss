<?php

declare(strict_types=1);

namespace App\Tests\Moderation\Application;

use App\Moderation\Application\Cleanup\CloseReportsOfDestroyedPaste;
use App\Moderation\Application\Dismiss\DismissReportCommand;
use App\Moderation\Application\Dismiss\DismissReportHandler;
use App\Moderation\Application\Exception\ReportActionNotAllowed;
use App\Moderation\Application\File\FileAbuseReportCommand;
use App\Moderation\Application\File\FileAbuseReportHandler;
use App\Moderation\Domain\AbuseReport;
use App\Moderation\Domain\Event\AbuseReported;
use App\Moderation\Domain\Exception\InvalidReportData;
use App\Moderation\Domain\Exception\ReportAlreadyClosed;
use App\Moderation\Domain\Exception\ReportNotFound;
use App\Moderation\Domain\ValueObject\ReportStatus;
use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\Event\PasteBurned;
use App\Paste\Domain\Event\PasteDeleted;
use App\Paste\Domain\Event\PasteDeletedByModerator;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\Base64Url;
use App\Shared\Domain\UserId;
use App\Tests\Identity\Support\RecordingEventBus;
use App\Tests\Moderation\Support\InMemoryAbuseReportRepository;
use App\Tests\Moderation\Support\StubModerationAuthorization;
use App\Tests\Moderation\Support\StubPasteExistence;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ModerationHandlersTest extends TestCase
{
    private InMemoryAbuseReportRepository $reports;
    private RecordingEventBus $events;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->reports = new InMemoryAbuseReportRepository();
        $this->events = new RecordingEventBus();
        $this->clock = new MockClock('2026-10-13 12:00:00');
    }

    public function test_an_anonymous_user_can_report_an_existing_paste(): void
    {
        $paste = PasteId::generate();

        $this->file($paste, existing: [$paste])(new FileAbuseReportCommand($paste->value, 'spam', 'huele a spam'));

        self::assertSame(1, $this->reports->count());
        self::assertSame('huele a spam', $this->reports->all()[0]->comment()->value);
        self::assertInstanceOf(AbuseReported::class, $this->events->published[0]);
    }

    public function test_reporting_a_nonexistent_paste_is_silently_ignored(): void
    {
        $unknown = PasteId::generate();

        $this->file($unknown, existing: [])(new FileAbuseReportCommand($unknown->value, 'spam', null));

        self::assertSame(0, $this->reports->count());
        self::assertSame([], $this->events->published);
    }

    public function test_format_errors_are_reported_even_when_the_paste_does_not_exist(): void
    {
        $unknown = PasteId::generate();
        $handler = $this->file($unknown, existing: []);

        foreach ([
            new FileAbuseReportCommand($unknown->value, 'no-existe', null),
            new FileAbuseReportCommand($unknown->value, 'spam', str_repeat('x', 281)),
            new FileAbuseReportCommand($unknown->value, 'spam', '#'.Base64Url::encode(random_bytes(32))),
        ] as $command) {
            try {
                $handler($command);
                self::fail('Debió rechazar el formato.');
            } catch (InvalidReportData) {
            }
        }

        $this->addToAssertionCount(1);
    }

    public function test_a_signed_in_user_reports_a_paste_only_once(): void
    {
        $paste = PasteId::generate();
        $user = UserId::generate();
        $handler = $this->file($paste, existing: [$paste], actor: $user);

        $handler(new FileAbuseReportCommand($paste->value, 'spam', null));
        $handler(new FileAbuseReportCommand($paste->value, 'malware', 'otra vez'));

        self::assertSame(1, $this->reports->count());
    }

    public function test_different_users_can_each_report_the_same_paste(): void
    {
        $paste = PasteId::generate();

        foreach ([UserId::generate(), UserId::generate(), null] as $actor) {
            $this->file($paste, existing: [$paste], actor: $actor)(new FileAbuseReportCommand($paste->value, 'spam', null));
        }

        self::assertSame(3, $this->reports->count());
    }

    public function test_open_reports_per_paste_are_capped(): void
    {
        $paste = PasteId::generate();
        $handler = $this->file($paste, existing: [$paste]);

        for ($i = 0; $i < AbuseReport::MAX_OPEN_PER_PASTE + 10; ++$i) {
            $handler(new FileAbuseReportCommand($paste->value, 'spam', null));
        }

        self::assertSame(AbuseReport::MAX_OPEN_PER_PASTE, $this->reports->count());
    }

    public function test_a_reviewer_dismisses_a_report(): void
    {
        $paste = PasteId::generate();
        $this->file($paste, existing: [$paste])(new FileAbuseReportCommand($paste->value, 'spam', null));
        $report = $this->reports->all()[0];
        $reviewer = UserId::generate();

        $this->dismiss($reviewer, true)(new DismissReportCommand($report->id()->value));

        self::assertSame(ReportStatus::Dismissed, $report->status());
        self::assertTrue($report->closedBy()->equals($reviewer));
    }

    public function test_without_review_permission_nothing_changes(): void
    {
        $paste = PasteId::generate();
        $this->file($paste, existing: [$paste])(new FileAbuseReportCommand($paste->value, 'spam', null));
        $report = $this->reports->all()[0];

        try {
            $this->dismiss(UserId::generate(), false)(new DismissReportCommand($report->id()->value));
            self::fail('Debió denegar.');
        } catch (ReportActionNotAllowed) {
        }

        self::assertTrue($report->isOpen());
    }

    public function test_dismissing_unknown_or_closed_reports_fails(): void
    {
        $paste = PasteId::generate();
        $this->file($paste, existing: [$paste])(new FileAbuseReportCommand($paste->value, 'spam', null));
        $report = $this->reports->all()[0];
        $handler = $this->dismiss(UserId::generate(), true);

        $handler(new DismissReportCommand($report->id()->value));

        try {
            $handler(new DismissReportCommand($report->id()->value));
            self::fail('Debió rechazar un reporte ya cerrado.');
        } catch (ReportAlreadyClosed) {
        }

        $this->expectException(ReportNotFound::class);
        $handler(new DismissReportCommand(\App\Moderation\Domain\ValueObject\ReportId::generate()->value));
    }

    public function test_any_destruction_resolves_every_open_report_of_that_paste_only(): void
    {
        $now = $this->clock->now();
        $cleanup = new CloseReportsOfDestroyedPaste($this->reports, $this->events);

        $destructions = [
            'deleted' => fn (PasteId $p) => $cleanup->onPasteDeleted(new PasteDeleted($p, DeletionReason::Token, $now)),
            'moderator' => fn (PasteId $p) => $cleanup->onPasteDeletedByModerator(new PasteDeletedByModerator($p, UserId::generate(), $now)),
            'burned' => fn (PasteId $p) => $cleanup->onPasteBurned(new PasteBurned($p, $now)),
        ];

        foreach ($destructions as $label => $destroy) {
            $target = PasteId::generate();
            $bystander = PasteId::generate();
            $handler = $this->file($target, existing: [$target, $bystander]);
            $handler(new FileAbuseReportCommand($target->value, 'spam', null));
            $handler(new FileAbuseReportCommand($target->value, 'malware', null));
            $handler(new FileAbuseReportCommand($bystander->value, 'spam', null));

            $destroy($target);

            self::assertSame(0, $this->reports->countOpenForPaste($target), $label);
            self::assertSame(1, $this->reports->countOpenForPaste($bystander), $label);
        }
    }

    private function file(PasteId $paste, array $existing, ?UserId $actor = null): FileAbuseReportHandler
    {
        $stub = new StubPasteExistence();
        foreach ($existing as $id) {
            $stub->with($id);
        }

        return new FileAbuseReportHandler($this->reports, $stub, new StubModerationAuthorization($actor), $this->events, $this->clock);
    }

    private function dismiss(UserId $actor, bool $canReview): DismissReportHandler
    {
        return new DismissReportHandler($this->reports, new StubModerationAuthorization($actor, $canReview), $this->events, $this->clock);
    }
}