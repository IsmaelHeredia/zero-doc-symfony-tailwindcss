<?php

declare(strict_types=1);

namespace App\Tests\Organization\Domain;

use App\Organization\Domain\Event\PasteTagged;
use App\Organization\Domain\Event\PasteUntagged;
use App\Organization\Domain\Event\TagCreated;
use App\Organization\Domain\Exception\InvalidTagData;
use App\Organization\Domain\Exception\TagFull;
use App\Organization\Domain\Tag;
use App\Organization\Domain\ValueObject\TagId;
use App\Organization\Domain\ValueObject\TagName;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TagTest extends TestCase
{
    public function test_creating_a_tag_records_tag_created(): void
    {
        $owner = UserId::generate();

        $tag = $this->tag($owner);

        $events = $tag->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TagCreated::class, $events[0]);
        self::assertTrue($tag->isOwnedBy($owner));
        self::assertFalse($tag->isOwnedBy(UserId::generate()));
    }

    public function test_attaching_is_idempotent_and_records_one_event(): void
    {
        $tag = $this->tag();
        $tag->pullDomainEvents();
        $paste = PasteId::generate();

        $tag->attach($paste, $this->now());
        $tag->attach($paste, $this->now());

        self::assertTrue($tag->has($paste));
        self::assertCount(1, $tag->pasteIds());
        $events = $tag->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PasteTagged::class, $events[0]);
    }

    public function test_detaching_records_an_event_only_if_attached(): void
    {
        $tag = $this->tag();
        $paste = PasteId::generate();
        $tag->attach($paste, $this->now());
        $tag->pullDomainEvents();

        $tag->detach(PasteId::generate(), $this->now());
        self::assertSame([], $tag->pullDomainEvents());

        $tag->detach($paste, $this->now());
        self::assertFalse($tag->has($paste));
        self::assertInstanceOf(PasteUntagged::class, $tag->pullDomainEvents()[0]);
    }

    public function test_a_tag_has_a_bounded_size(): void
    {
        $tag = $this->tag();
        for ($i = 0; $i < Tag::MAX_PASTES; ++$i) {
            $tag->attach(PasteId::generate(), $this->now());
        }

        $this->expectException(TagFull::class);
        $tag->attach(PasteId::generate(), $this->now());
    }

    public function test_a_full_tag_still_accepts_an_already_attached_paste(): void
    {
        $tag = $this->tag();
        $first = PasteId::generate();
        $tag->attach($first, $this->now());
        for ($i = 1; $i < Tag::MAX_PASTES; ++$i) {
            $tag->attach(PasteId::generate(), $this->now());
        }

        $tag->attach($first, $this->now());

        self::assertCount(Tag::MAX_PASTES, $tag->pasteIds());
    }

    public function test_names_are_normalized(): void
    {
        self::assertSame('trabajo urgente', TagName::fromString("  Trabajo \t URGENTE ")->value);
        self::assertTrue(TagName::fromString('Café')->equals(TagName::fromString('café')));
    }

    #[DataProvider('invalidNames')]
    public function test_it_rejects_invalid_names(string $name): void
    {
        $this->expectException(InvalidTagData::class);
        TagName::fromString($name);
    }

    public static function invalidNames(): iterable
    {
        yield 'vacío' => [''];
        yield 'solo espacios' => ['   '];
        yield 'empieza por guion' => ['-trabajo'];
        yield 'símbolos' => ['<script>'];
        yield 'demasiado largo' => [str_repeat('a', 33)];
        yield 'utf8 inválido' => ["a\xB1\x31"];
    }

    private function tag(?UserId $owner = null): Tag
    {
        return Tag::create(TagId::generate(), $owner ?? UserId::generate(), TagName::fromString('trabajo'), $this->now());
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-13 12:00:00');
    }
}