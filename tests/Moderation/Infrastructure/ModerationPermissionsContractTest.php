<?php

declare(strict_types=1);

namespace App\Tests\Moderation\Infrastructure;

use App\Identity\Application\SeedRoles\DefaultRoles;
use App\Moderation\Infrastructure\Security\ModerationPermissions;
use PHPUnit\Framework\TestCase;

final class ModerationPermissionsContractTest extends TestCase
{
    public function test_every_permission_moderation_consumes_exists_in_the_identity_catalog(): void
    {
        $catalog = array_merge(...array_values(DefaultRoles::definition()));

        foreach ((new \ReflectionClass(ModerationPermissions::class))->getConstants() as $name => $code) {
            self::assertContains($code, $catalog, sprintf('ModerationPermissions::%s (%s) no existe en DefaultRoles.', $name, $code));
        }
    }

    public function test_only_moderation_roles_review_reports(): void
    {
        $holders = array_keys(array_filter(
            DefaultRoles::definition(),
            static fn (array $permissions): bool => in_array(ModerationPermissions::REPORT_REVIEW, $permissions, true),
        ));
        sort($holders);

        self::assertSame(['admin', 'moderator'], $holders);
    }
}