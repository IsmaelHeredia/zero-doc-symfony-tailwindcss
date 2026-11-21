<?php

declare(strict_types=1);

namespace App\Moderation\Infrastructure\Persistence\Doctrine;

use App\Moderation\Domain\AbuseReport;
use App\Moderation\Domain\AbuseReportRepository;
use App\Moderation\Domain\Exception\ReportAlreadyFiled;
use App\Moderation\Domain\Exception\ReportNotFound;
use App\Moderation\Domain\ValueObject\ReportId;
use App\Moderation\Domain\ValueObject\ReportStatus;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAbuseReportRepository implements AbuseReportRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(AbuseReport $report): void
    {
        try {
            $this->em->persist($report);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw ReportAlreadyFiled::create();
        }
    }

    public function getForUpdate(ReportId $id): AbuseReport
    {
        return $this->em->find(AbuseReport::class, $id, LockMode::PESSIMISTIC_WRITE) ?? throw ReportNotFound::withId($id);
    }

    public function findOpenByPaste(PasteId $pasteId): array
    {
        $reports = $this->em
            ->createQuery('SELECT r FROM '.AbuseReport::class.' r WHERE r.pasteId = :paste AND r.status = :status')
            ->setParameter('paste', $pasteId->value)
            ->setParameter('status', ReportStatus::Open->value)
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();

        return $reports;
    }

    public function countOpenForPaste(PasteId $pasteId): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM abuse_report WHERE paste_id = ? AND status = ?',
            [$pasteId->value, ReportStatus::Open->value],
        );
    }

    public function hasReportFrom(PasteId $pasteId, UserId $reporter): bool
    {
        return false !== $this->connection()->fetchOne(
            'SELECT 1 FROM abuse_report WHERE paste_id = ? AND reporter_id = ?',
            [$pasteId->value, $reporter->value],
        );
    }

    private function connection(): Connection
    {
        return $this->em->getConnection();
    }
}