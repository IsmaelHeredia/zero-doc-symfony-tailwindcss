<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure;

use App\Identity\Application\SeedRoles\DefaultRoles;
use App\Organization\Infrastructure\Security\OrganizationPermissions;
use PHPUnit\Framework\TestCase;

final class OrganizationPermissionsContractTest extends TestCase
{
    public function test_every_permission_organization_consumes_exists_in_the_identity_catalog(): void
    {
        $catalog = array_merge(...array_values(DefaultRoles::definition()));

        foreach ((new \ReflectionClass(OrganizationPermissions::class))->getConstants() as $name => $code) {
            self::assertContains($code, $catalog, sprintf('OrganizationPermissions::%s (%s) no existe en DefaultRoles.', $name, $code));
        }
    }

    public function test_only_members_manage_tags(): void
    {
        $holders = array_keys(array_filter(
            DefaultRoles::definition(),
            static fn (array $permissions): bool => in_array(OrganizationPermissions::TAG_MANAGE_OWN, $permissions, true),
        ));

        self::assertSame(['member'], $holders);
    }
}