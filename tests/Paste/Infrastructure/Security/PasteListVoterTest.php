<?php

declare(strict_types=1);

namespace App\Tests\Paste\Infrastructure\Security;

use App\Paste\Infrastructure\Security\PasteListVoter;
use App\Paste\Infrastructure\Security\PastePermissions;
use App\Tests\Paste\Support\SecurityFixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PasteListVoterTest extends TestCase
{
    public function test_only_the_list_own_permission_grants_listing(): void
    {
        $voter = new PasteListVoter();
        $lister = SecurityFixtures::token(SecurityFixtures::user([PastePermissions::LIST_OWN]));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($lister, null, [PasteListVoter::LIST_OWN]));
    }

    public function test_other_permissions_and_anonymous_are_denied(): void
    {
        $voter = new PasteListVoter();
        $moderator = SecurityFixtures::token(SecurityFixtures::user([PastePermissions::DELETE_ANY, PastePermissions::CREATE]));

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($moderator, null, [PasteListVoter::LIST_OWN]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), null, [PasteListVoter::LIST_OWN]));
    }

    public function test_audit_read_and_list_own_are_independent(): void
    {
        $voter = new PasteListVoter();
        $auditor = SecurityFixtures::token(SecurityFixtures::user([PastePermissions::AUDIT_READ]));
        $lister = SecurityFixtures::token(SecurityFixtures::user([PastePermissions::LIST_OWN]));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($auditor, null, [PasteListVoter::AUDIT_READ]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($auditor, null, [PasteListVoter::LIST_OWN]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($lister, null, [PasteListVoter::AUDIT_READ]));
    }

}