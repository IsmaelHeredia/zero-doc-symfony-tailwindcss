<?php

declare(strict_types=1);

namespace App\Tests\Organization\Support;

use App\Organization\Domain\Exception\TagAlreadyExists;
use App\Organization\Domain\Exception\TagNotFound;
use App\Organization\Domain\Tag;
use App\Organization\Domain\TagRepository;
use App\Organization\Domain\ValueObject\TagId;
use App\Organization\Domain\ValueObject\TagName;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;

final class InMemoryTagRepository implements TagRepository
{
    private array $tags = [];

    public function save(Tag $tag): void
    {
        foreach ($this->tags as $other) {
            if (!$other->id()->equals($tag->id()) && $other->ownerId()->equals($tag->ownerId()) && $other->name()->equals($tag->name())) {
                throw TagAlreadyExists::withName($tag->name());
            }
        }
        $this->tags[$tag->id()->value] = $tag;
    }

    public function getOwnedForUpdate(TagId $id, UserId $owner): Tag
    {
        $tag = $this->tags[$id->value] ?? null;

        return null !== $tag && $tag->isOwnedBy($owner) ? $tag : throw TagNotFound::withId($id);
    }

    public function findByOwnerAndName(UserId $owner, TagName $name): ?Tag
    {
        foreach ($this->tags as $tag) {
            if ($tag->isOwnedBy($owner) && $tag->name()->equals($name)) {
                return $tag;
            }
        }

        return null;
    }

    public function countByOwner(UserId $owner): int
    {
        return count(array_filter($this->tags, static fn (Tag $t): bool => $t->isOwnedBy($owner)));
    }

    public function remove(Tag $tag): void
    {
        unset($this->tags[$tag->id()->value]);
    }

    public function forgetPaste(PasteId $pasteId): void
    {
        foreach ($this->tags as $tag) {
            $tag->detach($pasteId, new \DateTimeImmutable());
            $tag->pullDomainEvents();
        }
    }

    public function has(TagId $id): bool
    {
        return isset($this->tags[$id->value]);
    }
}