<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final readonly class UserId implements \Stringable
{
    private const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::UUID_V4, $value)) {
            throw new \InvalidArgumentException('UserId inválido (se espera UUID v4).');
        }

        return new self($value);
    }

    public static function generate(): self
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);

        return new self(sprintf('%s-%s-%s-%s-%s',
            substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20, 12)
        ));
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