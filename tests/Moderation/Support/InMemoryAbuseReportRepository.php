<?php

declare(strict_types=1);

namespace App\Tests\Moderation\Support;

use App\Moderation\Domain\AbuseReport;
use App\Moderation\Domain\AbuseReportRepository;
use App\Moderation\Domain\Exception\ReportAlreadyFiled;
use App\Moderation\Domain\Exception\ReportNotFound;
use App\Moderation\Domain\ValueObject\ReportId;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;

final class InMemoryAbuseReportRepository implements AbuseReportRepository
{
    private array $reports = [];

    private array $byReporter = [];

    public function save(AbuseReport $report): void
    {
        $reporter = $this->reporterOf($report);
        if (null !== $reporter) {
            $key = $report->pasteId()->value.'|'.$reporter;
            if (isset($this->byReporter[$key]) && $this->byReporter[$key] !== $report->id()->value) {
                throw ReportAlreadyFiled::create();
            }
            $this->byReporter[$key] = $report->id()->value;
        }
        $this->reports[$report->id()->value] = $report;
    }

    public function getForUpdate(ReportId $id): AbuseReport
    {
        return $this->reports[$id->value] ?? throw ReportNotFound::withId($id);
    }

    public function findOpenByPaste(PasteId $pasteId): array
    {
        return array_values(array_filter(
            $this->reports,
            static fn (AbuseReport $r): bool => $r->isOpen() && $r->pasteId()->equals($pasteId),
        ));
    }

    public function countOpenForPaste(PasteId $pasteId): int
    {
        return count($this->findOpenByPaste($pasteId));
    }

    public function hasReportFrom(PasteId $pasteId, UserId $reporter): bool
    {
        return isset($this->byReporter[$pasteId->value.'|'.$reporter->value]);
    }

    public function count(): int
    {
        return count($this->reports);
    }

    public function all(): array
    {
        return array_values($this->reports);
    }

    private function reporterOf(AbuseReport $report): ?string
    {
        return (fn (): ?string => $this->reporterId?->value)->call($report);
    }
}