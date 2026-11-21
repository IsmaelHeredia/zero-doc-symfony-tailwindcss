<?php

declare(strict_types=1);

namespace App\Moderation\Domain\ValueObject;

use App\Moderation\Domain\Exception\InvalidReportData;
use App\Shared\Domain\Uuid4;

final readonly class ReportId implements \Stringable
{
    private function __construct(public string $value)
    {
    }

    public static function generate(): self
    {
        return new self(Uuid4::generate());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid4::isValid($value)) {
            throw InvalidReportData::because('ReportId inválido (se espera UUID v4).');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}