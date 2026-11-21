<?php

declare(strict_types=1);

namespace App\Tests\Paste\Application;

use App\Paste\Application\Audit\RecordPasteDeletionAudit;
use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\Event\PasteBurned;
use App\Paste\Domain\Event\PasteDeleted;
use App\Paste\Domain\Event\PasteDeletedByModerator;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;
use App\Tests\Paste\Support\InMemoryDeletionLog;
use PHPUnit\Framework\TestCase;

final class RecordPasteDeletionAuditTest extends TestCase
{
    private InMemoryDeletionLog $log;
    private RecordPasteDeletionAudit $audit;

    protected function setUp(): void
    {
        $this->log = new InMemoryDeletionLog();
        $this->audit = new RecordPasteDeletionAudit($this->log);
    }

    public function test_owner_deletion_is_logged_with_the_owner_as_actor(): void
    {
        $id = PasteId::generate();
        $owner = UserId::generate();

        $this->audit->onPasteDeleted(new PasteDeleted($id, DeletionReason::Owner, $this->now(), $owner));

        $this->assertLogged($id, DeletionReason::Owner, $owner);
    }

    public function test_moderation_is_logged_with_the_moderator_as_actor(): void
    {
        $id = PasteId::generate();
        $moderator = UserId::generate();

        $this->audit->onPasteDeletedByModerator(new PasteDeletedByModerator($id, $moderator, $this->now()));

        $this->assertLogged($id, DeletionReason::Moderator, $moderator);
    }

    public function test_token_and_expired_deletions_have_no_actor(): void
    {
        $token = PasteId::generate();
        $expired = PasteId::generate();

        $this->audit->onPasteDeleted(new PasteDeleted($token, DeletionReason::Token, $this->now()));
        $this->audit->onPasteDeleted(new PasteDeleted($expired, DeletionReason::Expired, $this->now()));

        $this->assertLogged($token, DeletionReason::Token, null, 0);
        $this->assertLogged($expired, DeletionReason::Expired, null, 1);
    }

    public function test_burn_is_logged_without_actor(): void
    {
        $id = PasteId::generate();

        $this->audit->onPasteBurned(new PasteBurned($id, $this->now()));

        $this->assertLogged($id, DeletionReason::Burned, null);
    }

    private function assertLogged(PasteId $id, DeletionReason $reason, ?UserId $actor, int $index = 0): void
    {
        $entry = $this->log->entries[$index];

        self::assertTrue($entry['pasteId']->equals($id));
        self::assertSame($reason, $entry['reason']);
        if (null === $actor) {
            self::assertNull($entry['actor']);
        } else {
            self::assertTrue($entry['actor']->equals($actor));
        }
        self::assertEquals($this->now(), $entry['at']);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-13 12:00:00');
    }
}