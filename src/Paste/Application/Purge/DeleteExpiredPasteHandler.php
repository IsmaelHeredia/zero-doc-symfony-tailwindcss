<?php

declare(strict_types=1);

namespace App\Paste\Application\Purge;

use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\Exception\PasteNotFound;
use App\Paste\Domain\PasteRepository;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Application\EventBus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteExpiredPasteHandler
{
    public function __construct(
        private PasteRepository $pastes,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DeleteExpiredPasteCommand $command): bool
    {
        $now = $this->clock->now();

        try {
            $paste = $this->pastes->getForUpdate(PasteId::fromString($command->id));
        } catch (PasteNotFound) {
            return false;
        }

        if (!$paste->isExpiredAt($now)) {
            return false;
        }

        $paste->destroy(DeletionReason::Expired, $now);
        $this->pastes->remove($paste);
        $this->events->publish(...$paste->pullDomainEvents());

        return true;
    }
}