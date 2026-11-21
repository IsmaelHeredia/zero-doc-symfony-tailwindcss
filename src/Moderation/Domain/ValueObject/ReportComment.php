<?php

declare(strict_types=1);

namespace App\Moderation\Domain\ValueObject;

use App\Moderation\Domain\Exception\InvalidReportData;

final readonly class ReportComment implements \Stringable
{
    public const MAX_LENGTH = 280;

    private function __construct(public string $value)
    {
    }

    public static function fromNullable(?string $value): ?self
    {
        if (null === $value || '' === self::normalize($value)) {
            return null;
        }

        return self::fromString($value);
    }

    public static function fromString(string $value): self
    {
        $normalized = self::normalize($value);

        if ('' === $normalized) {
            throw InvalidReportData::because('El comentario no puede estar vacío.');
        }
        if (!mb_check_encoding($value, 'UTF-8') || 1 === preg_match('/\p{C}/u', $normalized)) {
            throw InvalidReportData::because('Comentario inválido.');
        }
        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw InvalidReportData::because(sprintf('El comentario no puede superar los %d caracteres.', self::MAX_LENGTH));
        }
        if (1 === preg_match('/#[A-Za-z0-9_-]{16,}/', $normalized) || 1 === preg_match('/[A-Za-z0-9_-]{40,}/', $normalized)) {
            throw InvalidReportData::because('El comentario no puede incluir enlaces con clave: indica solo el motivo.');
        }

        return new self($normalized);
    }

    private static function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}