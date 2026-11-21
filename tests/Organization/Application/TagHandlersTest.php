<?php

declare(strict_types=1);

namespace App\Tests\Organization\Application;

use App\Organization\Application\Attach\AttachPasteToTagCommand;
use App\Organization\Application\Attach\AttachPasteToTagHandler;
use App\Organization\Application\Cleanup\RemoveDestroyedPasteFromTags;
use App\Organization\Application\Create\CreateTagCommand;
use App\Organization\Application\Create\CreateTagHandler;
use App\Organization\Application\Delete\DeleteTagCommand;
use App\Organization\Application\Delete\DeleteTagHandler;
use App\Organization\Application\Detach\DetachPasteFromTagCommand;
use App\Organization\Application\Detach\DetachPasteFromTagHandler;
use App\Organization\Application\Exception\PasteNotOwnedByActor;
use App\Organization\Application\Exception\TagActionNotAllowed;
use App\Organization\Application\Exception\TagLimitReached;
use App\Organization\Domain\Event\PasteTagged;
use App\Organization\Domain\Exception\TagAlreadyExists;
use App\Organization\Domain\Exception\TagNotFound;
use App\Organization\Domain\Tag;
use App\Organization\Domain\ValueObject\TagId;
use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\Event\PasteBurned;
use App\Paste\Domain\Event\PasteDeleted;
use App\Paste\Domain\Event\PasteDeletedByModerator;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;
use App\Tests\Identity\Support\RecordingEventBus;
use App\Tests\Organization\Support\InMemoryTagRepository;
use App\Tests\Organization\Support\StubPasteOwnership;
use App\Tests\Organization\Support\StubTagAuthorization;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class TagHandlersTest extends TestCase
{
    private InMemoryTagRepository $tags;
    private RecordingEventBus $events;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->tags = new InMemoryTagRepository();
        $this->events = new RecordingEventBus();
        $this->clock = new MockClock('2026-10-13 12:00:00');
    }

    public function test_it_creates_a_tag_with_a_normalized_name(): void
    {
        $owner = UserId::generate();

        $created = $this->create($owner)(new CreateTagCommand('  Trabajo  '));

        self::assertSame('trabajo', $created->name);
        self::assertSame(1, $this->tags->countByOwner($owner));
    }

    public function test_names_are_unique_per_owner_but_not_across_owners(): void
    {
        $ana = UserId::generate();
        $this->create($ana)(new CreateTagCommand('trabajo'));

        try {
            $this->create($ana)(new CreateTagCommand('TRABAJO'));
            self::fail('Debió rechazar el duplicado.');
        } catch (TagAlreadyExists) {
        }

        $this->create(UserId::generate())(new CreateTagCommand('trabajo'));
        $this->addToAssertionCount(1);
    }

    public function test_an_owner_cannot_exceed_the_tag_limit(): void
    {
        $owner = UserId::generate();
        $handler = $this->create($owner);
        for ($i = 0; $i < Tag::MAX_PER_OWNER; ++$i) {
            $handler(new CreateTagCommand('tag '.$i));
        }

        $this->expectException(TagLimitReached::class);
        $handler(new CreateTagCommand('uno mas'));
    }

    public function test_without_permission_nothing_is_stored(): void
    {
        try {
            $this->create(null)(new CreateTagCommand('trabajo'));
            self::fail('Debió denegar.');
        } catch (TagActionNotAllowed) {
        }

        self::assertSame([], $this->events->published);
    }

    public function test_an_owner_tags_their_own_paste(): void
    {
        [$owner, $tagId] = $this->ownerWithTag();
        $paste = PasteId::generate();

        $this->attach($owner, (new StubPasteOwnership())->owns($owner, $paste))(new AttachPasteToTagCommand($tagId, $paste->value));

        self::assertTrue($this->tags->getOwnedForUpdate(TagId::fromString($tagId), $owner)->has($paste));
        self::assertInstanceOf(PasteTagged::class, end($this->events->published));
    }

    public function test_pastes_that_are_not_yours_cannot_be_tagged(): void
    {
        [$owner, $tagId] = $this->ownerWithTag();
        $strangers = PasteId::generate(); 

        try {
            $this->attach($owner, new StubPasteOwnership())(new AttachPasteToTagCommand($tagId, $strangers->value));
            self::fail('Debió rechazar.');
        } catch (PasteNotOwnedByActor) {
        }

        self::assertFalse($this->tags->getOwnedForUpdate(TagId::fromString($tagId), $owner)->has($strangers));
    }

    public function test_someone_elses_tag_looks_nonexistent(): void
    {
        [, $tagId] = $this->ownerWithTag();
        $intruder = UserId::generate();
        $paste = PasteId::generate();
        $ownership = (new StubPasteOwnership())->owns($intruder, $paste);

        foreach ([
            fn () => $this->attach($intruder, $ownership)(new AttachPasteToTagCommand($tagId, $paste->value)),
            fn () => (new DetachPasteFromTagHandler($this->tags, new StubTagAuthorization($intruder), $this->events, $this->clock))(new DetachPasteFromTagCommand($tagId, $paste->value)),
            fn () => (new DeleteTagHandler($this->tags, new StubTagAuthorization($intruder)))(new DeleteTagCommand($tagId)),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('Debió tratarlo como inexistente.');
            } catch (TagNotFound) {
            }
        }

        self::assertTrue($this->tags->has(TagId::fromString($tagId)));
    }

    public function test_detach_and_delete_tag(): void
    {
        [$owner, $tagId] = $this->ownerWithTag();
        $paste = PasteId::generate();
        $this->attach($owner, (new StubPasteOwnership())->owns($owner, $paste))(new AttachPasteToTagCommand($tagId, $paste->value));

        (new DetachPasteFromTagHandler($this->tags, new StubTagAuthorization($owner), $this->events, $this->clock))(new DetachPasteFromTagCommand($tagId, $paste->value));
        self::assertFalse($this->tags->getOwnedForUpdate(TagId::fromString($tagId), $owner)->has($paste));

        (new DeleteTagHandler($this->tags, new StubTagAuthorization($owner)))(new DeleteTagCommand($tagId));
        self::assertFalse($this->tags->has(TagId::fromString($tagId)));
    }

    public function test_any_destruction_removes_the_paste_from_every_tag(): void
    {
        $owner = UserId::generate();
        $now = $this->clock->now();
        $cleanup = new RemoveDestroyedPasteFromTags($this->tags);

        $events = [
            'deleted' => fn (PasteId $p) => $cleanup->onPasteDeleted(new PasteDeleted($p, DeletionReason::Expired, $now)),
            'moderator' => fn (PasteId $p) => $cleanup->onPasteDeletedByModerator(new PasteDeletedByModerator($p, UserId::generate(), $now)),
            'burned' => fn (PasteId $p) => $cleanup->onPasteBurned(new PasteBurned($p, $now)),
        ];

        foreach ($events as $label => $destroy) {
            $paste = PasteId::generate();
            $ownership = (new StubPasteOwnership())->owns($owner, $paste);
            $tagA = $this->create($owner)(new CreateTagCommand('a '.$label))->id;
            $tagB = $this->create($owner)(new CreateTagCommand('b '.$label))->id;
            foreach ([$tagA, $tagB] as $tagId) {
                $this->attach($owner, $ownership)(new AttachPasteToTagCommand($tagId, $paste->value));
            }

            $destroy($paste);

            foreach ([$tagA, $tagB] as $tagId) {
                self::assertFalse($this->tags->getOwnedForUpdate(TagId::fromString($tagId), $owner)->has($paste), $label);
            }
        }
    }

    private function create(?UserId $actor): CreateTagHandler
    {
        return new CreateTagHandler($this->tags, new StubTagAuthorization($actor), $this->events, $this->clock);
    }

    private function attach(UserId $actor, StubPasteOwnership $ownership): AttachPasteToTagHandler
    {
        return new AttachPasteToTagHandler($this->tags, new StubTagAuthorization($actor), $ownership, $this->events, $this->clock);
    }

    private function ownerWithTag(): array
    {
        $owner = UserId::generate();

        return [$owner, $this->create($owner)(new CreateTagCommand('trabajo'))->id];
    }
}