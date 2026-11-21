<?php

declare(strict_types=1);

namespace App\Paste\Application\Query;

use App\Paste\Domain\ValueObject\PasteId;
use Psr\Clock\ClockInterface;

final readonly class GetPasteAccessInfo
{
    public function __construct(private PasteAccessReader $reader, private ClockInterface $clock)
    {
    }

    public function __invoke(string $id): ?PasteAccessInfo
    {
        return $this->reader->find(PasteId::fromString($id), $this->clock->now());
    }
}