<?php

declare(strict_types=1);

namespace App\Tests\Identity\Application;

use App\Identity\Application\Admin\Exception\AdminActionNotAllowed;
use App\Identity\Application\Admin\Query\AdminAuditEntry;
use App\Identity\Application\Admin\Query\AdminAuditReader;
use App\Identity\Application\Admin\Query\ListAdminAudit;
use App\Shared\Domain\UserId;
use App\Tests\Identity\Support\StubAdminAuthorization;
use PHPUnit\Framework\TestCase;

final class ListAdminAuditTest extends TestCase
{
    public function test_it_reads_with_the_fixed_limit_when_allowed(): void
    {
        $reader = $this->reader();

        $entries = (new ListAdminAudit(new StubAdminAuthorization(UserId::generate(), canAudit: true), $reader))();

        self::assertSame(ListAdminAudit::LIMIT, $reader->limit);
        self::assertCount(1, $entries);
    }

    public function test_user_manage_and_role_manage_are_not_enough_and_the_reader_is_never_touched(): void
    {
        $reader = $this->reader();

        try {
            (new ListAdminAudit(new StubAdminAuthorization(UserId::generate(), canUsers: true, canRoles: true), $reader))();
            self::fail('Debió denegar.');
        } catch (AdminActionNotAllowed) {
        }

        self::assertNull($reader->limit);
    }

    private function reader(): AdminAuditReader
    {
        return new class implements AdminAuditReader {
            public ?int $limit = null;

            public function latest(int $limit): array
            {
                $this->limit = $limit;

                return [new AdminAuditEntry('2026-10-13T12:00:00+00:00', 'role_assigned', 'a', null, 'admin', null)];
            }
        };
    }
}