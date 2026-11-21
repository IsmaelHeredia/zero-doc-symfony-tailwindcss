<?php

declare(strict_types=1);

namespace App\Tests\Paste\Application;

use App\Paste\Application\Exception\ListingNotAllowed;
use App\Paste\Application\Query\DeletionLogReader;
use App\Paste\Application\Query\ListPasteDeletions;
use App\Shared\Domain\UserId;
use App\Tests\Paste\Support\StubPasteListingAuthorization;
use PHPUnit\Framework\TestCase;

final class ListPasteDeletionsTest extends TestCase
{
    public function test_it_reads_the_log_with_the_fixed_limit_when_allowed(): void
    {
        $reader = $this->reader();

        (new ListPasteDeletions(new StubPasteListingAuthorization(UserId::generate()), $reader))();

        self::assertSame(ListPasteDeletions::LIMIT, $reader->limit);
    }

    public function test_without_audit_read_the_reader_is_never_touched(): void
    {
        $reader = $this->reader();

        try {
            (new ListPasteDeletions(new StubPasteListingAuthorization(null), $reader))();
            self::fail('Debió denegar.');
        } catch (ListingNotAllowed) {
        }

        self::assertNull($reader->limit);
    }

    private function reader(): DeletionLogReader
    {
        return new class implements DeletionLogReader {
            public ?int $limit = null;

            public function latest(int $limit): array
            {
                $this->limit = $limit;

                return [];
            }
        };
    }
}