<?php

declare(strict_types=1);

namespace App\Tests\Paste\Infrastructure\Security;

use App\Identity\Application\SeedRoles\DefaultRoles;
use App\Paste\Infrastructure\Security\PastePermissions;
use PHPUnit\Framework\TestCase;

final class PastePermissionsContractTest extends TestCase
{
    public function test_every_permission_paste_consumes_exists_in_the_identity_catalog(): void
    {
        $catalog = array_merge(...array_values(DefaultRoles::definition()));

        foreach ((new \ReflectionClass(PastePermissions::class))->getConstants() as $name => $code) {
            self::assertContains($code, $catalog, sprintf('PastePermissions::%s (%s) no existe en DefaultRoles.', $name, $code));
        }
    }

    public function test_only_moderation_roles_can_delete_any_paste(): void
    {
        $holders = array_keys(array_filter(
            DefaultRoles::definition(),
            static fn (array $permissions): bool => in_array(PastePermissions::DELETE_ANY, $permissions, true),
        ));
        sort($holders);

        self::assertSame(['admin', 'moderator'], $holders);
    }
}