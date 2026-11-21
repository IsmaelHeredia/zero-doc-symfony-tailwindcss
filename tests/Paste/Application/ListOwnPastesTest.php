<?php

declare(strict_types=1);

namespace App\Tests\Paste\Application;

use App\Paste\Application\Exception\ListingNotAllowed;
use App\Paste\Application\Query\ListOwnPastes;
use App\Paste\Application\Query\OwnPasteReader;
use App\Paste\Application\Query\OwnPastesPage;
use App\Shared\Domain\UserId;
use App\Tests\Paste\Support\StubPasteListingAuthorization;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ListOwnPastesTest extends TestCase
{
    public function test_it_lists_for_the_session_owner_with_the_fixed_limit(): void
    {
        $owner = UserId::generate();
        $reader = $this->recordingReader();

        (new ListOwnPastes(new StubPasteListingAuthorization($owner), $reader, new MockClock('2026-10-13 12:00:00')))();

        self::assertTrue($reader->owner->equals($owner));
        self::assertSame(ListOwnPastes::LIMIT, $reader->limit);
    }

    public function test_without_permission_the_reader_is_never_touched(): void
    {
        $reader = $this->recordingReader();

        try {
            (new ListOwnPastes(new StubPasteListingAuthorization(null), $reader, new MockClock('2026-10-13 12:00:00')))();
            self::fail('Debió denegar.');
        } catch (ListingNotAllowed) {
        }

        self::assertNull($reader->owner);
    }

    private function recordingReader(): OwnPasteReader
    {
        return new class implements OwnPasteReader {
            public ?UserId $owner = null;
            public ?int $limit = null;

            public function pastesOf(UserId $owner, \DateTimeImmutable $now, int $limit): OwnPastesPage
            {
                $this->owner = $owner;
                $this->limit = $limit;

                return new OwnPastesPage([], false);
            }
        };
    }
}