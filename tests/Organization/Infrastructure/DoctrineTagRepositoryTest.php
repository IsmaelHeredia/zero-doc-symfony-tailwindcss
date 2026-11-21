<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure;

use App\Organization\Domain\Exception\TagAlreadyExists;
use App\Organization\Domain\Exception\TagNotFound;
use App\Organization\Domain\Tag;
use App\Organization\Domain\ValueObject\TagId;
use App\Organization\Domain\ValueObject\TagName;
use App\Organization\Infrastructure\Persistence\Doctrine\DoctrineTagRepository;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineTagRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DoctrineTagRepository $tags;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->tags = new DoctrineTagRepository($this->em);
        $this->now = new \DateTimeImmutable('2026-10-13 12:00:00');
    }

    public function test_pastes_roundtrip_through_the_pivot_table(): void
    {
        $owner = UserId::generate();
        $a = PasteId::generate();
        $b = PasteId::generate();
        $tag = $this->newTag($owner);
        $tag->attach($a, $this->now);
        $tag->attach($b, $this->now);
        $this->tags->save($tag);
        $this->em->clear();

        $loaded = $this->em->wrapInTransaction(fn () => $this->tags->getOwnedForUpdate($tag->id(), $owner));
        self::assertTrue($loaded->has($a));
        self::assertTrue($loaded->has($b));

        $loaded->detach($a, $this->now);
        $this->tags->save($loaded);
        $this->em->clear();

        $reloaded = $this->em->wrapInTransaction(fn () => $this->tags->getOwnedForUpdate($tag->id(), $owner));
        self::assertFalse($reloaded->has($a));
        self::assertTrue($reloaded->has($b));
    }

    public function test_another_owner_cannot_load_the_tag(): void
    {
        $tag = $this->newTag($owner = UserId::generate());
        $this->tags->save($tag);
        $this->em->clear();

        $this->expectException(TagNotFound::class);
        $this->em->wrapInTransaction(fn () => $this->tags->getOwnedForUpdate($tag->id(), UserId::generate()));
    }

    public function test_names_are_unique_per_owner_at_database_level(): void
    {
        $owner = UserId::generate();
        $name = TagName::fromString('t'.bin2hex(random_bytes(4)));
        $this->tags->save(Tag::create(TagId::generate(), $owner, $name, $this->now));

        $this->expectException(TagAlreadyExists::class);
        $this->tags->save(Tag::create(TagId::generate(), $owner, $name, $this->now));
    }

    public function test_forget_paste_removes_it_from_every_tag_of_every_owner(): void
    {
        $paste = PasteId::generate();
        $tagA = $this->newTag(UserId::generate());
        $tagB = $this->newTag(UserId::generate());
        foreach ([$tagA, $tagB] as $tag) {
            $tag->attach($paste, $this->now);
            $this->tags->save($tag);
        }

        $this->tags->forgetPaste($paste);

        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM paste_tag WHERE paste_id = ?', [$paste->value]));
    }

    public function test_deleting_a_tag_cascades_to_its_pivot_rows(): void
    {
        $paste = PasteId::generate();
        $tag = $this->newTag(UserId::generate());
        $tag->attach($paste, $this->now);
        $this->tags->save($tag);

        $this->tags->remove($tag);

        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM paste_tag WHERE tag_id = ?', [$tag->id()->value]));
    }

    private function newTag(UserId $owner): Tag
    {
        return Tag::create(TagId::generate(), $owner, TagName::fromString('t'.bin2hex(random_bytes(4))), $this->now);
    }

    private function connection(): Connection
    {
        return self::getContainer()->get('doctrine.dbal.default_connection');
    }
}