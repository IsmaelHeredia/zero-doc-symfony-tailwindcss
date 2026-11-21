<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure;

use App\Organization\Infrastructure\Security\OrganizationPermissions;
use App\Organization\Infrastructure\Security\TagVoter;
use App\Tests\Paste\Support\SecurityFixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class TagVoterTest extends TestCase
{
    public function test_anonymous_cannot_do_anything(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, (new TagVoter())->vote(new NullToken(), null, [TagVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, (new TagVoter())->vote(new NullToken(), null, [TagVoter::LIST_PASTES]));
    }

    public function test_each_action_depends_on_its_own_permission(): void
    {
        $onlyManage = SecurityFixtures::token(SecurityFixtures::user([OrganizationPermissions::TAG_MANAGE_OWN]));
        $onlyList = SecurityFixtures::token(SecurityFixtures::user([OrganizationPermissions::PASTE_LIST_OWN]));
        $voter = new TagVoter();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($onlyManage, null, [TagVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($onlyManage, null, [TagVoter::LIST_PASTES]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($onlyList, null, [TagVoter::LIST_PASTES]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($onlyList, null, [TagVoter::MANAGE]));
    }

    public function test_a_moderator_permission_grants_nothing_here(): void
    {
        $moderator = SecurityFixtures::token(SecurityFixtures::user(['paste.delete_any']));

        self::assertSame(VoterInterface::ACCESS_DENIED, (new TagVoter())->vote($moderator, null, [TagVoter::MANAGE]));
    }
}