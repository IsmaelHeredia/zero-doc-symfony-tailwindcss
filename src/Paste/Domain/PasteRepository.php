<?php

declare(strict_types=1);

namespace App\Paste\Domain;

use App\Paste\Domain\Exception\PasteNotFound;
use App\Paste\Domain\ValueObject\PasteId;

interface PasteRepository
{
    public function save(Paste $paste): void;

    public function get(PasteId $id): Paste;

    public function remove(Paste $paste): void;

    public function getForUpdate(PasteId $id): Paste;
}