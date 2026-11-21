<?php

declare(strict_types=1);

namespace App\Organization\Domain;

use App\Organization\Domain\Exception\TagAlreadyExists;
use App\Organization\Domain\Exception\TagNotFound;
use App\Organization\Domain\ValueObject\TagId;
use App\Organization\Domain\ValueObject\TagName;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;

interface TagRepository
{
    public function save(Tag $tag): void;

    public function getOwnedForUpdate(TagId $id, UserId $owner): Tag;

    public function findByOwnerAndName(UserId $owner, TagName $name): ?Tag;

    public function countByOwner(UserId $owner): int;

    public function remove(Tag $tag): void;

    public function forgetPaste(PasteId $pasteId): void;
}