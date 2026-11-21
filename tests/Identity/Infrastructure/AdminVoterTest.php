<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure;

use App\Identity\Infrastructure\Security\AdminPermissions;
use App\Identity\Infrastructure\Security\AdminVoter;
use App\Identity\Application\SeedRoles\DefaultRoles;
use App\Tests\Paste\Support\SecurityFixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AdminVoterTest extends TestCase
{
    public function test_each_area_depends_on_its_own_permission(): void
    {
        $voter = new AdminVoter();
        $users = SecurityFixtures::token(SecurityFixtures::user([AdminPermissions::USER_MANAGE]));
        $roles = SecurityFixtures::token(SecurityFixtures::user([AdminPermissions::ROLE_MANAGE]));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($users, null, [AdminVoter::USERS]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($users, null, [AdminVoter::ROLES]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($roles, null, [AdminVoter::ROLES]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($roles, null, [AdminVoter::USERS]));
    }

    public function test_moderation_permissions_and_anonymous_get_nothing(): void
    {
        $voter = new AdminVoter();
        $moderator = SecurityFixtures::token(SecurityFixtures::user(['paste.delete_any', 'report.review']));

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($moderator, null, [AdminVoter::USERS]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), null, [AdminVoter::ROLES]));
    }

    public function test_contract_with_the_identity_catalog_and_only_admin_administers(): void
    {
        $definition = DefaultRoles::definition();
        $catalog = array_merge(...array_values($definition));

        foreach ((new \ReflectionClass(AdminPermissions::class))->getConstants() as $code) {
            self::assertContains($code, $catalog);
            $holders = array_keys(array_filter($definition, static fn(array $p): bool => in_array($code, $p, true)));
            self::assertSame(['admin'], $holders);
        }
    }

    public function test_audit_read_is_its_own_permission(): void
    {
        $voter = new AdminVoter();
        $auditor = SecurityFixtures::token(SecurityFixtures::user([AdminPermissions::AUDIT_READ]));
        $administrator = SecurityFixtures::token(SecurityFixtures::user([AdminPermissions::USER_MANAGE, AdminPermissions::ROLE_MANAGE]));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($auditor, null, [AdminVoter::AUDIT]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($auditor, null, [AdminVoter::USERS]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($administrator, null, [AdminVoter::AUDIT]));
    }

}