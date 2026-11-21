<?php

declare(strict_types=1);

namespace App\Tests\Paste\Support;

use App\Paste\Domain\Exception\PasteNotFound;
use App\Paste\Domain\Paste;
use App\Paste\Domain\PasteRepository;
use App\Paste\Domain\ValueObject\PasteId;

final class InMemoryPasteRepository implements PasteRepository
{
    private array $pastes = [];

    public function save(Paste $paste): void
    {
        $this->pastes[$paste->id()->value] = $paste;
    }

    public function get(PasteId $id): Paste
    {
        return $this->pastes[$id->value] ?? throw PasteNotFound::withId($id);
    }

    public function getForUpdate(PasteId $id): Paste
    {
        return $this->get($id);
    }

    public function remove(Paste $paste): void
    {
        unset($this->pastes[$paste->id()->value]);
    }

    public function has(PasteId $id): bool
    {
        return isset($this->pastes[$id->value]);
    }

    public function count(): int
    {
        return count($this->pastes);
    }
}