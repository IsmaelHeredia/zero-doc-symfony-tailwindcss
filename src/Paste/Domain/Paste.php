<?php

declare(strict_types=1);

namespace App\Paste\Domain;

use App\Paste\Domain\Event\PasteBurned;
use App\Paste\Domain\Event\PasteCreated;
use App\Paste\Domain\Event\PasteDeleted;
use App\Paste\Domain\Event\PasteDeletedByModerator;
use App\Paste\Domain\Event\PasteViewed;
use App\Paste\Domain\Exception\InvalidPasteData;
use App\Paste\Domain\Exception\PasteConsumed;
use App\Paste\Domain\Exception\PasteExpired;
use App\Paste\Domain\ValueObject\Ciphertext;
use App\Paste\Domain\ValueObject\ContentSizeLimit;
use App\Paste\Domain\ValueObject\DeleteToken;
use App\Paste\Domain\ValueObject\DeleteTokenHash;
use App\Paste\Domain\ValueObject\ExpirationPolicy;
use App\Paste\Domain\ValueObject\InitializationVector;
use App\Paste\Domain\ValueObject\PasteId;
use App\Paste\Domain\ValueObject\ViewPolicy;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\UserId;
use App\Paste\Domain\ValueObject\KeyDerivation;

class Paste extends AggregateRoot
{
    private int $views = 0;

    private function __construct(
        private PasteId $id,
        private Ciphertext $ciphertext,
        private InitializationVector $iv,
        private ExpirationPolicy $expiration,
        private ViewPolicy $viewPolicy,
        private ?UserId $ownerId,
        private ?DeleteTokenHash $deleteTokenHash,
        private \DateTimeImmutable $createdAt,
        private ?KeyDerivation $keyDerivation = null,
    ) {
    }

    public static function createAnonymous(
        PasteId $id,
        Ciphertext $ciphertext,
        InitializationVector $iv,
        ExpirationPolicy $expiration,
        ViewPolicy $viewPolicy,
        ContentSizeLimit $sizeLimit,
        DeleteTokenHash $deleteTokenHash,
        \DateTimeImmutable $now,
        ?KeyDerivation $keyDerivation = null,
    ): self {
        return self::build($id, $ciphertext, $iv, $expiration, $viewPolicy, $sizeLimit, null, $deleteTokenHash, $now, $keyDerivation);
    }

    public static function createOwned(
        PasteId $id,
        Ciphertext $ciphertext,
        InitializationVector $iv,
        ExpirationPolicy $expiration,
        ViewPolicy $viewPolicy,
        ContentSizeLimit $sizeLimit,
        UserId $ownerId,
        \DateTimeImmutable $now,
        ?KeyDerivation $keyDerivation = null,
    ): self {
        return self::build($id, $ciphertext, $iv, $expiration, $viewPolicy, $sizeLimit, $ownerId, null, $now, $keyDerivation);
    }

    private static function build(
        PasteId $id,
        Ciphertext $ciphertext,
        InitializationVector $iv,
        ExpirationPolicy $expiration,
        ViewPolicy $viewPolicy,
        ContentSizeLimit $sizeLimit,
        ?UserId $ownerId,
        ?DeleteTokenHash $deleteTokenHash,
        \DateTimeImmutable $now,
        ?KeyDerivation $keyDerivation = null,
    ): self {
        if (!$sizeLimit->allows($ciphertext)) {
            throw InvalidPasteData::because(sprintf('El paste supera el límite de %d bytes.', $sizeLimit->maxBytes));
        }

        $paste = new self($id, $ciphertext, $iv, $expiration, $viewPolicy, $ownerId, $deleteTokenHash, $now, $keyDerivation);
        $paste->record(new PasteCreated($id, $ownerId, $now));

        return $paste;
    }

    public function view(\DateTimeImmutable $now): EncryptedPayload
    {
        if ($this->expiration->isExpiredAt($now)) {
            throw PasteExpired::withId($this->id);
        }
        if ($this->isConsumed()) {
            throw PasteConsumed::withId($this->id);
        }

        ++$this->views;
        $this->record(new PasteViewed($this->id, $now));
        if ($this->isConsumed()) {
            $this->record(new PasteBurned($this->id, $now));
        }

        $kdf = $this->keyDerivation;
        if (null !== $kdf && !$kdf->isConfigured()) {
            $kdf = null;
        }

        return new EncryptedPayload($this->ciphertext, $this->iv, $kdf);
    }

    public function destroy(DeletionReason $reason, \DateTimeImmutable $now, ?UserId $actor = null): void
    {
        $this->record(match ($reason) {
            DeletionReason::Moderator => new PasteDeletedByModerator(
                $this->id,
                $actor ?? throw InvalidPasteData::because('El borrado por moderación requiere identificar al moderador.'),
                $now,
            ),
            DeletionReason::Owner => new PasteDeleted(
                $this->id,
                $reason,
                $now,
                $actor ?? throw InvalidPasteData::because('El borrado por el dueño requiere identificar al usuario.'),
            ),
            DeletionReason::Burned => throw InvalidPasteData::because('El burn lo emite view(), no se invoca manualmente.'),
            default => new PasteDeleted($this->id, $reason, $now),
        });
    }


    public function isOwnedBy(UserId $userId): bool
    {
        return null !== $this->ownerId && $this->ownerId->equals($userId);
    }

    public function acceptsDeleteToken(DeleteToken $token): bool
    {
        return null !== $this->deleteTokenHash && $this->deleteTokenHash->matches($token);
    }

    public function isConsumed(): bool
    {
        return $this->viewPolicy->isExhaustedAfter($this->views);
    }

    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return $this->expiration->isExpiredAt($now);
    }

    public function id(): PasteId
    {
        return $this->id;
    }

    public function views(): int
    {
        return $this->views;
    }
}