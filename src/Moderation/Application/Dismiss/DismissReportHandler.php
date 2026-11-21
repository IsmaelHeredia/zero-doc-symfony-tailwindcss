<?php

declare(strict_types=1);

namespace App\Moderation\Application\Dismiss;

use App\Moderation\Application\Port\ModerationAuthorization;
use App\Moderation\Domain\AbuseReportRepository;
use App\Moderation\Domain\ValueObject\ReportId;
use App\Shared\Application\EventBus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DismissReportHandler
{
    public function __construct(
        private AbuseReportRepository $reports,
        private ModerationAuthorization $authorization,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DismissReportCommand $command): void
    {
        $reviewer = $this->authorization->requireReview();
        $report = $this->reports->getForUpdate(ReportId::fromString($command->reportId));

        $report->dismiss($reviewer, $this->clock->now());

        $this->reports->save($report);
        $this->events->publish(...$report->pullDomainEvents());
    }
}