<?php

declare(strict_types=1);

namespace App\Tests\Moderation\Infrastructure;

use App\Moderation\Infrastructure\Security\ModerationPermissions;
use App\Moderation\Infrastructure\Security\ModerationVoter;
use App\Tests\Paste\Support\SecurityFixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ModerationVoterTest extends TestCase
{
    public function test_only_the_review_permission_grants_review(): void
    {
        $voter = new ModerationVoter();
        $reviewer = SecurityFixtures::token(SecurityFixtures::user([ModerationPermissions::REPORT_REVIEW]));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($reviewer, null, [ModerationVoter::REVIEW]));
    }

    public function test_deleting_pastes_is_not_reviewing_reports(): void
    {
        $voter = new ModerationVoter();
        $deleter = SecurityFixtures::token(SecurityFixtures::user(['paste.delete_any']));
        $member = SecurityFixtures::token(SecurityFixtures::user(['paste.create', 'tag.manage_own']));

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($deleter, null, [ModerationVoter::REVIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($member, null, [ModerationVoter::REVIEW]));
    }

    public function test_anonymous_cannot_review(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, (new ModerationVoter())->vote(new NullToken(), null, [ModerationVoter::REVIEW]));
    }
}