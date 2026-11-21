<?php

declare(strict_types=1);

namespace App\Tests\Paste\Application;

use App\Paste\Application\Purge\DeleteExpiredPasteCommand;
use App\Paste\Application\Purge\DeleteExpiredPasteHandler;
use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\Event\PasteDeleted;
use App\Paste\Domain\Paste;
use App\Paste\Domain\ValueObject\PasteId;
use App\Tests\Identity\Support\RecordingEventBus;
use App\Tests\Paste\Support\InMemoryPasteRepository;
use App\Tests\Paste\Support\PasteFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class DeleteExpiredPasteHandlerTest extends TestCase
{
    private InMemoryPasteRepository $pastes;
    private RecordingEventBus $events;

    protected function setUp(): void
    {
        $this->pastes = new InMemoryPasteRepository();
        $this->events = new RecordingEventBus();
    }

    public function test_it_destroys_a_paste_at_the_exact_expiry_instant(): void
    {
        $paste = $this->stored(PasteFactory::anonymous());

        $destroyed = $this->handler('2026-10-13 13:00:00')(new DeleteExpiredPasteCommand($paste->id()->value));

        self::assertTrue($destroyed);
        self::assertFalse($this->pastes->has($paste->id()));
        $event = $this->events->published[0];
        self::assertInstanceOf(PasteDeleted::class, $event);
        self::assertSame(DeletionReason::Expired, $event->reason);
        self::assertNull($event->actorId);
    }

    public function test_it_leaves_a_live_paste_untouched(): void
    {
        $paste = $this->stored(PasteFactory::anonymous());

        $destroyed = $this->handler('2026-10-13 12:59:59')(new DeleteExpiredPasteCommand($paste->id()->value));

        self::assertFalse($destroyed);
        self::assertTrue($this->pastes->has($paste->id()));
        self::assertSame([], $this->events->published);
    }

    public function test_it_is_idempotent_when_the_paste_is_already_gone(): void
    {
        $destroyed = $this->handler('2026-10-13 13:00:00')(new DeleteExpiredPasteCommand(PasteId::generate()->value));

        self::assertFalse($destroyed);
        self::assertSame([], $this->events->published);
    }

    private function stored(Paste $paste): Paste
    {
        $this->pastes->save($paste);
        $paste->pullDomainEvents();

        return $paste;
    }

    private function handler(string $now): DeleteExpiredPasteHandler
    {
        return new DeleteExpiredPasteHandler($this->pastes, $this->events, new MockClock($now));
    }
}