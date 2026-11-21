<?php

declare(strict_types=1);

namespace App\Paste\Application\Create;

use App\Paste\Application\Exception\CreationNotAllowed;
use App\Paste\Application\Port\AuthorizationChecker;
use App\Paste\Domain\Paste;
use App\Paste\Domain\PasteRepository;
use App\Paste\Domain\ValueObject\Ciphertext;
use App\Paste\Domain\ValueObject\ContentSizeLimit;
use App\Paste\Domain\ValueObject\DeleteToken;
use App\Paste\Domain\ValueObject\ExpirationPolicy;
use App\Paste\Domain\ValueObject\InitializationVector;
use App\Paste\Domain\ValueObject\PasteId;
use App\Paste\Domain\ValueObject\ViewPolicy;
use App\Paste\Domain\ValueObject\KeyDerivation;
use App\Shared\Application\EventBus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreatePasteHandler
{
    public function __construct(
        private PasteRepository $pastes,
        private AuthorizationChecker $authorization,
        private EventBus $events,
        private ClockInterface $clock,
        private int $defaultMaxTtlSeconds,
        private int $defaultMaxBytes,
        private int $largeMaxBytes,
    ) {
    }

    public function __invoke(CreatePasteCommand $command): CreatedPaste
    {
        $rights = $this->authorization->creationRights();
        if (!$rights->canCreate) {
            throw CreationNotAllowed::create();
        }

        $now = $this->clock->now();
        $id = PasteId::generate();
        $ciphertext = Ciphertext::fromBase64Url($command->ciphertext);
        $iv = InitializationVector::fromBase64Url($command->iv);

        $expiration = ExpirationPolicy::create(
            $command->ttlSeconds,
            $now,
            $rights->canCreatePermanent ? null : $this->defaultMaxTtlSeconds,
        );
        $sizeLimit = ContentSizeLimit::ofBytes($rights->canCreateLarge ? $this->largeMaxBytes : $this->defaultMaxBytes);
        $viewPolicy = ViewPolicy::create($command->burnAfterRead, $command->maxViews);
        $keyDerivation = KeyDerivation::fromNullable($command->kdfSalt, $command->kdfIterations);

        if (null === $rights->actor) {
            $token = DeleteToken::generate();
            $paste = Paste::createAnonymous($id, $ciphertext, $iv, $expiration, $viewPolicy, $sizeLimit, $token->hash(), $now, $keyDerivation);
        } else {
            $token = null;
            $paste = Paste::createOwned($id, $ciphertext, $iv, $expiration, $viewPolicy, $sizeLimit, $rights->actor, $now, $keyDerivation);
        }

        $this->pastes->save($paste);
        $this->events->publish(...$paste->pullDomainEvents());

        return new CreatedPaste($paste->id()->value, $token?->value);
    }
}