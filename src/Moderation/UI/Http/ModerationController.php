<?php

declare(strict_types=1);

namespace App\Moderation\UI\Http;

use App\Moderation\Application\Dismiss\DismissReportCommand;
use App\Moderation\Application\Query\ListOpenReports;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ModerationController
{
    use HandleTrait;

    private const NO_STORE = ['Cache-Control' => 'no-store'];
    private const REPORT_ID = '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    public function __construct(MessageBusInterface $commandBus, private readonly ListOpenReports $listOpenReports)
    {
        $this->messageBus = $commandBus;
    }

    #[Route('/moderation/reports', name: 'moderation_reports', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $reports = array_map(static fn ($r): array => [
            'id' => $r->id,
            'pasteId' => $r->pasteId,
            'reason' => $r->reason,
            'comment' => $r->comment,
            'createdAt' => $r->createdAt,
            'openReportsForPaste' => $r->openReportsForPaste,
        ], ($this->listOpenReports)());

        return new JsonResponse(['reports' => $reports], 200, self::NO_STORE);
    }

    #[Route('/moderation/reports/{id}/dismiss', name: 'moderation_dismiss', requirements: ['id' => self::REPORT_ID], methods: ['POST'])]
    public function dismiss(string $id): Response
    {
        $this->handle(new DismissReportCommand($id));

        return new Response(null, 204, self::NO_STORE);
    }
}