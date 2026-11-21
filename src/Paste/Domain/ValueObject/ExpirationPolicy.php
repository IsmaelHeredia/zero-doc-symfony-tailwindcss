<?php

declare(strict_types=1);

namespace App\Paste\Domain\ValueObject;

use App\Paste\Domain\Exception\InvalidPasteData;

final readonly class ExpirationPolicy
{
    private function __construct(public ?\DateTimeImmutable $expiresAt)
    {
    }

    private const HARD_MAX_TTL_SECONDS = 315_360_000;

    public static function create(?int $ttlSeconds, \DateTimeImmutable $now, ?int $maxTtlSeconds): self
    {
        if (null === $ttlSeconds) {
            if (null !== $maxTtlSeconds) {
                throw InvalidPasteData::because('No se permiten pastes permanentes.');
            }

            return new self(null);
        }

        if ($ttlSeconds < 1) {
            throw InvalidPasteData::because('El TTL debe ser positivo.');
        }

        if ($ttlSeconds > self::HARD_MAX_TTL_SECONDS) {
            throw InvalidPasteData::because('El TTL supera el máximo absoluto (10 años).');
        }

        if (null !== $maxTtlSeconds && $ttlSeconds > $maxTtlSeconds) {
            throw InvalidPasteData::because(sprintf('El TTL máximo permitido es de %d segundos.', $maxTtlSeconds));
        }

        return new self($now->modify(sprintf('+%d seconds', $ttlSeconds)));
    }

    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return null !== $this->expiresAt && $now >= $this->expiresAt;
    }

    public static function fromStoredExpiry(?\DateTimeImmutable $expiresAt): self
    {
        return new self($expiresAt);
    }

}