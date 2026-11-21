<?php

declare(strict_types=1);

namespace App\Paste\Application\Read;

use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\EncryptedPayload;
use App\Paste\Domain\PasteRepository;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Application\EventBus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ReadPasteHandler
{
    public function __construct(
        private PasteRepository $pastes,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ReadPasteCommand $command): ?EncryptedPayload
    {
        $now = $this->clock->now();
        $paste = $this->pastes->getForUpdate(PasteId::fromString($command->id));

        if ($paste->isExpiredAt($now)) {
            $paste->destroy(DeletionReason::Expired, $now);
            $this->pastes->remove($paste);
            $this->events->publish(...$paste->pullDomainEvents());

            return null;
        }

        $payload = $paste->view($now);

        if ($paste->isConsumed()) {
            $this->pastes->remove($paste);
        } else {
            $this->pastes->save($paste);
        }
        $this->events->publish(...$paste->pullDomainEvents());

        return $payload;
    }
}