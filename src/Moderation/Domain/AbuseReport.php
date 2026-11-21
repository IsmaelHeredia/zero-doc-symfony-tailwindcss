<?php

declare(strict_types=1);

namespace App\Moderation\Domain;

use App\Moderation\Domain\Event\AbuseReported;
use App\Moderation\Domain\Event\ReportDismissed;
use App\Moderation\Domain\Event\ReportResolved;
use App\Moderation\Domain\Exception\ReportAlreadyClosed;
use App\Moderation\Domain\ValueObject\ReportComment;
use App\Moderation\Domain\ValueObject\ReportId;
use App\Moderation\Domain\ValueObject\ReportReason;
use App\Moderation\Domain\ValueObject\ReportStatus;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\UserId;

class AbuseReport extends AggregateRoot
{
    public const MAX_OPEN_PER_PASTE = 25;

    private ReportStatus $status;
    private ?\DateTimeImmutable $closedAt = null;
    private ?UserId $closedBy = null;

    private function __construct(
        private ReportId $id,
        private PasteId $pasteId,
        private ReportReason $reason,
        private ?ReportComment $comment,
        private ?UserId $reporterId,
        private \DateTimeImmutable $createdAt,
    ) {
        $this->status = ReportStatus::Open;
    }

    public static function file(
        ReportId $id,
        PasteId $pasteId,
        ReportReason $reason,
        ?ReportComment $comment,
        ?UserId $reporterId,
        \DateTimeImmutable $now,
    ): self {
        $report = new self($id, $pasteId, $reason, $comment, $reporterId, $now);
        $report->record(new AbuseReported($id, $pasteId, $reason, $now));

        return $report;
    }

    public function dismiss(UserId $reviewer, \DateTimeImmutable $now): void
    {
        if (!$this->isOpen()) {
            throw ReportAlreadyClosed::create();
        }

        $this->status = ReportStatus::Dismissed;
        $this->closedAt = $now;
        $this->closedBy = $reviewer;
        $this->record(new ReportDismissed($this->id, $reviewer, $now));
    }

    public function resolveBecausePasteDestroyed(\DateTimeImmutable $now): void
    {
        if (!$this->isOpen()) {
            return;
        }

        $this->status = ReportStatus::Resolved;
        $this->closedAt = $now;
        $this->record(new ReportResolved($this->id, $now));
    }

    public function isOpen(): bool
    {
        return ReportStatus::Open === $this->status;
    }

    public function id(): ReportId
    {
        return $this->id;
    }

    public function pasteId(): PasteId
    {
        return $this->pasteId;
    }

    public function reason(): ReportReason
    {
        return $this->reason;
    }

    public function comment(): ?ReportComment
    {
        return $this->comment;
    }

    public function status(): ReportStatus
    {
        return $this->status;
    }

    public function closedBy(): ?UserId
    {
        return $this->closedBy;
    }
}