<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Application\SeedRoles\DefaultRoles;
use App\Identity\Domain\Exception\InvalidRoleData;
use App\Identity\Domain\ValueObject\PermissionCode;
use PHPUnit\Framework\TestCase;

final class PermissionCatalogTest extends TestCase
{
    public function test_the_catalog_lists_exactly_every_permission_constant(): void
    {
        $constants = array_values((new \ReflectionClass(PermissionCode::class))->getConstants());
        $catalog = PermissionCode::catalog();
        sort($constants);
        sort($catalog);

        self::assertSame($constants, $catalog);
    }

    public function test_every_default_role_permission_belongs_to_the_catalog(): void
    {
        foreach (DefaultRoles::definition() as $permissions) {
            foreach ($permissions as $code) {
                self::assertContains($code, PermissionCode::catalog());
            }
        }
    }

    public function test_only_catalog_codes_are_accepted_from_outside(): void
    {
        self::assertSame('paste.delete_any', PermissionCode::fromCatalog('paste.delete_any')->value);

        foreach (['paste.read_any', 'paste.view_content', 'x.y', 'inventado.cosa'] as $unknown) {
            try {
                PermissionCode::fromCatalog($unknown);
                self::fail("$unknown no debería aceptarse.");
            } catch (InvalidRoleData) {
            }
        }
    }
}