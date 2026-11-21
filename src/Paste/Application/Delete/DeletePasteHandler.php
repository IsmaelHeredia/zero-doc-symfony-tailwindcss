<?php

declare(strict_types=1);

namespace App\Paste\Application\Delete;

use App\Paste\Application\Exception\DeletionNotAllowed;
use App\Paste\Application\Port\AuthorizationChecker;
use App\Paste\Domain\Exception\InvalidPasteData;
use App\Paste\Domain\PasteRepository;
use App\Paste\Domain\ValueObject\DeleteToken;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Application\EventBus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeletePasteHandler
{
    public function __construct(
        private PasteRepository $pastes,
        private AuthorizationChecker $authorization,
        private EventBus $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DeletePasteCommand $command): void
    {
        $paste = $this->pastes->getForUpdate(PasteId::fromString($command->id));

        $grant = $this->authorization->deletionGrantFor($paste, $this->parseToken($command->deleteToken));
        if (null === $grant) {
            throw DeletionNotAllowed::forPaste($paste->id());
        }

        $paste->destroy($grant->reason, $this->clock->now(), $grant->actor);
        $this->pastes->remove($paste);
        $this->events->publish(...$paste->pullDomainEvents());
    }

    private function parseToken(?string $raw): ?DeleteToken
    {
        if (null === $raw) {
            return null;
        }
        try {
            return DeleteToken::fromString($raw);
        } catch (InvalidPasteData) {
            return null;
        }
    }
}