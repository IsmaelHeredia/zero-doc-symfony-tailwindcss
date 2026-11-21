<?php

declare(strict_types=1);

namespace App\Moderation\Domain;

use App\Moderation\Domain\Exception\ReportAlreadyFiled;
use App\Moderation\Domain\Exception\ReportNotFound;
use App\Moderation\Domain\ValueObject\ReportId;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;

interface AbuseReportRepository
{
    public function save(AbuseReport $report): void;

    public function getForUpdate(ReportId $id): AbuseReport;

    public function findOpenByPaste(PasteId $pasteId): array;

    public function countOpenForPaste(PasteId $pasteId): int;

    public function hasReportFrom(PasteId $pasteId, UserId $reporter): bool;
}