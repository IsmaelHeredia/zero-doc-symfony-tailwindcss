<?php

declare(strict_types=1);

namespace App\Tests\Paste\Infrastructure\Security;

use App\Paste\Infrastructure\Security\PasteCreateVoter;
use App\Paste\Infrastructure\Security\PastePermissions;
use App\Tests\Paste\Support\SecurityFixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PasteCreateVoterTest extends TestCase
{
    public function test_anonymous_can_create_but_not_permanent_nor_large(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(PasteCreateVoter::CREATE, null, []));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(PasteCreateVoter::CREATE_PERMANENT, null, []));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(PasteCreateVoter::CREATE_LARGE, null, []));
    }

    public function test_a_user_without_paste_create_cannot_create(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(PasteCreateVoter::CREATE, []));
    }
    

    public function test_each_capability_depends_on_its_own_permission(): void
    {
        $onlyCreate = [PastePermissions::CREATE];

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(PasteCreateVoter::CREATE, $onlyCreate));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(PasteCreateVoter::CREATE_PERMANENT, $onlyCreate));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(PasteCreateVoter::CREATE_LARGE, $onlyCreate));

        $all = [PastePermissions::CREATE, PastePermissions::CREATE_PERMANENT, PastePermissions::LARGE];
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(PasteCreateVoter::CREATE_PERMANENT, $all));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(PasteCreateVoter::CREATE_LARGE, $all));
    }

    private function vote(string $attribute, ?array $permissions): int
    {
        $token = null === $permissions
            ? new NullToken()
            : SecurityFixtures::token(SecurityFixtures::user($permissions));

        return (new PasteCreateVoter())->vote($token, null, [$attribute]);
    }
}