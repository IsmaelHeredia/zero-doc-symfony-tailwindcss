<?php

declare(strict_types=1);

namespace App\Moderation\Application\File;

use App\Moderation\Application\Port\ModerationAuthorization;
use App\Moderation\Application\Port\PasteExistence;
use App\Moderation\Domain\AbuseReport;
use App\Moderation\Domain\AbuseReportRepository;
use App\Moderation\Domain\Exception\InvalidReportData;
use App\Moderation\Domain\ValueObject\ReportComment;
use App\Moderation\Domain\ValueObject\ReportId;
use App\Moderation\Domain\ValueObject\ReportReason;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Application\EventBus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class FileAbuseReportHandler
{
    public function __construct(
        private AbuseReportRepository $reports,
        private PasteExistence $pastes,
        private ModerationAuthorization $authorization,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(FileAbuseReportCommand $command): void
    {
        $pasteId = PasteId::fromString($command->pasteId);
        $reason = ReportReason::tryFrom($command->reason) ?? throw InvalidReportData::because(sprintf(
            'Motivo inválido (opciones: %s).',
            implode(', ', array_map(static fn (ReportReason $r): string => $r->value, ReportReason::cases())),
        ));
        $comment = ReportComment::fromNullable($command->comment);
        $reporter = $this->authorization->reporter();

        if (!$this->pastes->exists($pasteId)) {
            return;
        }
        if ($this->reports->countOpenForPaste($pasteId) >= AbuseReport::MAX_OPEN_PER_PASTE) {
            return;
        }
        if (null !== $reporter && $this->reports->hasReportFrom($pasteId, $reporter)) {
            return;
        }

        $report = AbuseReport::file(ReportId::generate(), $pasteId, $reason, $comment, $reporter, $this->clock->now());

        $this->reports->save($report);
        $this->events->publish(...$report->pullDomainEvents());
    }
}