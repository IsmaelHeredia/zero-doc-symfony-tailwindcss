<?php

declare(strict_types=1);

namespace App\Moderation\UI\Http;

use App\Moderation\Application\File\FileAbuseReportCommand;
use App\Moderation\Domain\Exception\InvalidReportData;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ReportController
{
    use HandleTrait;

    private const NO_STORE = ['Cache-Control' => 'no-store'];

    public function __construct(
        MessageBusInterface $commandBus,
        #[Autowire(service: 'limiter.abuse_report')] private readonly RateLimiterFactory $abuseReportLimiter,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/reports', name: 'report_file', methods: ['POST'])]
    public function file(Request $request): JsonResponse
    {
        $limit = $this->abuseReportLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            return new JsonResponse(
                ['error' => 'rate_limited', 'message' => 'Demasiados reportes. Inténtalo más tarde.'],
                429,
                self::NO_STORE + ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())],
            );
        }

        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidReportData::because('JSON inválido.');
        }
        $pasteId = is_array($data) ? ($data['pasteId'] ?? null) : null;
        $reason = is_array($data) ? ($data['reason'] ?? null) : null;
        $comment = is_array($data) ? ($data['comment'] ?? null) : null;

        if (!is_string($pasteId) || !is_string($reason) || !(null === $comment || is_string($comment))) {
            throw InvalidReportData::because('Se requieren "pasteId" y "reason" (y, opcionalmente, "comment").');
        }

        $this->handle(new FileAbuseReportCommand($pasteId, $reason, $comment));

        return new JsonResponse(['status' => 'received'], 202, self::NO_STORE);
    }
}