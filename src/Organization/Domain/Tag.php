<?php

declare(strict_types=1);

namespace App\Organization\Domain;

use App\Organization\Domain\Event\PasteTagged;
use App\Organization\Domain\Event\PasteUntagged;
use App\Organization\Domain\Event\TagCreated;
use App\Organization\Domain\Exception\TagFull;
use App\Organization\Domain\ValueObject\TagId;
use App\Organization\Domain\ValueObject\TagName;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\UserId;

class Tag extends AggregateRoot
{
    public const MAX_PER_OWNER = 50;
    public const MAX_PASTES = 500;

    private array $pasteIds = [];

    private function __construct(
        private TagId $id,
        private UserId $ownerId,
        private TagName $name,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(TagId $id, UserId $ownerId, TagName $name, \DateTimeImmutable $now): self
    {
        $tag = new self($id, $ownerId, $name, $now);
        $tag->record(new TagCreated($id, $ownerId, $now));

        return $tag;
    }

    public function attach(PasteId $pasteId, \DateTimeImmutable $now): void
    {
        if ($this->has($pasteId)) {
            return;
        }
        if (count($this->pasteIds) >= self::MAX_PASTES) {
            throw TagFull::create();
        }

        $this->pasteIds[] = $pasteId;
        $this->record(new PasteTagged($this->id, $pasteId, $now));
    }

    public function detach(PasteId $pasteId, \DateTimeImmutable $now): void
    {
        if (!$this->has($pasteId)) {
            return;
        }

        $this->pasteIds = array_values(array_filter(
            $this->pasteIds,
            static fn (PasteId $p): bool => !$p->equals($pasteId),
        ));
        $this->record(new PasteUntagged($this->id, $pasteId, $now));
    }

    public function has(PasteId $pasteId): bool
    {
        foreach ($this->pasteIds as $p) {
            if ($p->equals($pasteId)) {
                return true;
            }
        }

        return false;
    }

    public function isOwnedBy(UserId $userId): bool
    {
        return $this->ownerId->equals($userId);
    }

    public function pasteIds(): array
    {
        return $this->pasteIds;
    }

    public function id(): TagId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function name(): TagName
    {
        return $this->name;
    }
}