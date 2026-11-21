<?php

declare(strict_types=1);

namespace App\Tests\Paste\Application;

use App\Paste\Application\Delete\DeletePasteCommand;
use App\Paste\Application\Delete\DeletePasteHandler;
use App\Paste\Application\Exception\DeletionNotAllowed;
use App\Paste\Application\Port\DeletionGrant;
use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\Event\PasteDeleted;
use App\Paste\Domain\Event\PasteDeletedByModerator;
use App\Paste\Domain\Paste;
use App\Shared\Domain\UserId;
use App\Tests\Identity\Support\RecordingEventBus;
use App\Tests\Paste\Support\InMemoryPasteRepository;
use App\Tests\Paste\Support\PasteFactory;
use App\Tests\Paste\Support\StubAuthorizationChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class DeletePasteHandlerTest extends TestCase
{
    private InMemoryPasteRepository $pastes;
    private RecordingEventBus $events;

    public function test_moderation_deletes_physically_and_emits_the_dedicated_event(): void
    {
        $moderator = UserId::generate();
        $paste = $this->stored(PasteFactory::anonymous());

        $this->handler(new DeletionGrant(DeletionReason::Moderator, $moderator))(new DeletePasteCommand($paste->id()->value, null));

        self::assertFalse($this->pastes->has($paste->id()));
        self::assertCount(1, $this->events->published);
        self::assertInstanceOf(PasteDeletedByModerator::class, $this->events->published[0]);
        self::assertTrue($this->events->published[0]->moderatorId->equals($moderator));
    }

    public function test_owner_deletion_records_who_deleted(): void
    {
        $owner = UserId::generate();
        $paste = $this->stored(PasteFactory::owned($owner));

        $this->handler(new DeletionGrant(DeletionReason::Owner, $owner))(new DeletePasteCommand($paste->id()->value, null));

        $event = $this->events->published[0];
        self::assertInstanceOf(PasteDeleted::class, $event);
        self::assertSame(DeletionReason::Owner, $event->reason);
        self::assertTrue($event->actorId->equals($owner));
    }

    public function test_a_denied_deletion_changes_nothing(): void
    {
        $paste = $this->stored(PasteFactory::anonymous());

        try {
            $this->handler(null)(new DeletePasteCommand($paste->id()->value, null));
            self::fail('Debió denegar.');
        } catch (DeletionNotAllowed) {
        }

        self::assertTrue($this->pastes->has($paste->id()));
        self::assertSame([], $this->events->published);
    }

    private function stored(Paste $paste): Paste
    {
        $this->pastes ??= new InMemoryPasteRepository();
        $this->pastes->save($paste);
        $paste->pullDomainEvents();

        return $paste;
    }

    private function handler(?DeletionGrant $grant): DeletePasteHandler
    {
        $this->pastes ??= new InMemoryPasteRepository();
        $this->events = new RecordingEventBus();

        return new DeletePasteHandler(
            $this->pastes,
            new StubAuthorizationChecker(grant: $grant),
            $this->events,
            new MockClock('2026-10-13 12:00:00'),
        );
    }
}