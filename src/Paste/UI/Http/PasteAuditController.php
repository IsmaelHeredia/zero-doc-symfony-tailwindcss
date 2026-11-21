<?php

declare(strict_types=1);

namespace App\Paste\UI\Http;

use App\Paste\Application\Query\DeletionLogEntry;
use App\Paste\Application\Query\ListPasteDeletions;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class PasteAuditController
{
    public function __construct(private ListPasteDeletions $listDeletions)
    {
    }

    #[Route('/admin/audit/deletions', name: 'admin_audit_deletions', methods: ['GET'])]
    public function deletions(): JsonResponse
    {
        return new JsonResponse(['deletions' => array_map(static fn (DeletionLogEntry $e): array => [
            'deletedAt' => $e->deletedAt,
            'pasteId' => $e->pasteId,
            'reason' => $e->reason,
            'actorId' => $e->actorId,
        ], ($this->listDeletions)())], 200, ['Cache-Control' => 'no-store']);
    }
}