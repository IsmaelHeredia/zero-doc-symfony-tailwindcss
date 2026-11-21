<?php

declare(strict_types=1);

namespace App\Tests\Paste\Application;

use App\Paste\Application\Create\CreatePasteCommand;
use App\Paste\Application\Create\CreatePasteHandler;
use App\Paste\Application\Exception\CreationNotAllowed;
use App\Paste\Application\Port\CreationRights;
use App\Paste\Domain\Event\PasteCreated;
use App\Paste\Domain\Exception\InvalidPasteData;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\Base64Url;
use App\Shared\Domain\UserId;
use App\Tests\Identity\Support\RecordingEventBus;
use App\Tests\Paste\Support\InMemoryPasteRepository;
use App\Tests\Paste\Support\StubAuthorizationChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CreatePasteHandlerTest extends TestCase
{
    private const WEEK = 7 * 86400;

    private InMemoryPasteRepository $pastes;
    private RecordingEventBus $events;

    public function test_anonymous_gets_a_delete_token_and_default_limits(): void
    {
        $handler = $this->handler(new CreationRights(null, true, false, false));

        $created = $handler($this->command());

        self::assertNotNull($created->deleteToken);
        self::assertSame(43, strlen($created->deleteToken));
        self::assertNull($this->events->published[0]->ownerId);
        self::assertInstanceOf(PasteCreated::class, $this->events->published[0]);
    }

    public function test_anonymous_cannot_create_permanent_pastes_over_the_cap_or_over_the_size(): void
    {
        $handler = $this->handler(new CreationRights(null, true, false, false));

        foreach ([
            $this->command(ttl: null),
            $this->command(ttl: self::WEEK + 1),
            $this->command(bytes: 2000),
        ] as $command) {
            try {
                $handler($command);
                self::fail('Debió rechazar la creación.');
            } catch (InvalidPasteData) {
            }
        }
        self::assertSame(0, $this->pastes->count());
    }

    public function test_an_authenticated_user_creates_an_owned_paste_without_delete_token(): void
    {
        $actor = UserId::generate();
        $handler = $this->handler(new CreationRights($actor, true, false, false));

        $created = $handler($this->command());

        self::assertNull($created->deleteToken);
        $paste = $this->pastes->get(PasteId::fromString($created->id));
        self::assertTrue($paste->isOwnedBy($actor));
        self::assertTrue($this->events->published[0]->ownerId->equals($actor));
    }

    public function test_permanent_pastes_require_the_permanent_permission(): void
    {
        $without = $this->handler(new CreationRights(UserId::generate(), true, false, false));
        try {
            $without($this->command(ttl: null));
            self::fail('Debió exigir paste.create_permanent.');
        } catch (InvalidPasteData) {
        }

        $with = $this->handler(new CreationRights(UserId::generate(), true, true, false));
        self::assertNotEmpty($with($this->command(ttl: null))->id);
    }

    public function test_large_pastes_require_the_large_permission(): void
    {
        $without = $this->handler(new CreationRights(UserId::generate(), true, false, false));
        try {
            $without($this->command(bytes: 2000));
            self::fail('Debió exigir paste.large.');
        } catch (InvalidPasteData) {
        }

        $with = $this->handler(new CreationRights(UserId::generate(), true, false, true));
        self::assertNotEmpty($with($this->command(bytes: 2000))->id);

        $this->expectException(InvalidPasteData::class);
        $with($this->command(bytes: 6000));
    }

    public function test_a_user_without_create_permission_is_rejected_before_anything_is_stored(): void
    {
        $handler = $this->handler(new CreationRights(UserId::generate(), false, true, true));

        try {
            $handler($this->command());
            self::fail('Debió denegar.');
        } catch (CreationNotAllowed) {
        }

        self::assertSame(0, $this->pastes->count());
        self::assertSame([], $this->events->published);
    }

    private function handler(CreationRights $rights): CreatePasteHandler
    {
        $this->pastes = new InMemoryPasteRepository();
        $this->events = new RecordingEventBus();

        return new CreatePasteHandler(
            $this->pastes,
            new StubAuthorizationChecker($rights),
            $this->events,
            new MockClock('2026-10-13 12:00:00'),
            defaultMaxTtlSeconds: self::WEEK,
            defaultMaxBytes: 1000,
            largeMaxBytes: 5000,
        );
    }

    private function command(?int $ttl = 3600, int $bytes = 64): CreatePasteCommand
    {
        return new CreatePasteCommand(
            Base64Url::encode(random_bytes($bytes)),
            Base64Url::encode(random_bytes(12)),
            $ttl,
            false,
            null,
        );
    }

    public function test_the_kdf_parameters_are_stored_and_returned_on_read(): void
    {
        $handler = $this->handler(new CreationRights(null, true, false, false));
        $salt = Base64Url::encode(random_bytes(16));

        $created = $handler(new CreatePasteCommand(
            Base64Url::encode(random_bytes(64)),
            Base64Url::encode(random_bytes(12)),
            3600,
            false,
            null,
            $salt,
            600_000,
        ));

        $payload = $this->pastes->get(PasteId::fromString($created->id))->view(new \DateTimeImmutable('2026-10-13 12:00:00'));
        self::assertSame($salt, $payload->keyDerivation->salt);
        self::assertSame(600_000, $payload->keyDerivation->iterations);
    }

    public function test_weak_or_partial_kdf_parameters_store_nothing(): void
    {
        $handler = $this->handler(new CreationRights(null, true, false, false));
        $salt = Base64Url::encode(random_bytes(16));

        foreach ([[$salt, 1000], [$salt, null], [null, 600_000]] as [$s, $i]) {
            try {
                $handler(new CreatePasteCommand(
                    Base64Url::encode(random_bytes(64)),
                    Base64Url::encode(random_bytes(12)),
                    3600,
                    false,
                    null,
                    $s,
                    $i,
                ));
                self::fail('Debió rechazar el kdf.');
            } catch (InvalidPasteData) {
            }
        }

        self::assertSame(0, $this->pastes->count());
    }

}