<?php

declare(strict_types=1);

namespace App\Tests\Paste\Infrastructure\Security;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Paste\Domain\Paste;
use App\Paste\Domain\ValueObject\DeleteToken;
use App\Paste\Infrastructure\Security\PasteDeleteVoter;
use App\Paste\Infrastructure\Security\PasteDeletionRequest;
use App\Paste\Infrastructure\Security\PastePermissions;
use App\Tests\Paste\Support\PasteFactory;
use App\Tests\Paste\Support\SecurityFixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PasteDeleteVoterTest extends TestCase
{
    private const GRANTED = VoterInterface::ACCESS_GRANTED;
    private const DENIED = VoterInterface::ACCESS_DENIED;

    private PasteDeleteVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new PasteDeleteVoter();
    }

    public function test_owner_rule_grants_the_owner(): void
    {
        $owner = SecurityFixtures::user();

        self::assertSame(self::GRANTED, $this->vote(PasteDeleteVoter::OWNER, $owner, PasteFactory::owned($owner->actorId())));
    }

    public function test_owner_rule_denies_other_users_even_with_moderation_permission(): void
    {
        $other = SecurityFixtures::user([PastePermissions::DELETE_ANY]);

        self::assertSame(self::DENIED, $this->vote(PasteDeleteVoter::OWNER, $other, PasteFactory::owned(SecurityFixtures::user()->actorId())));
    }

    public function test_owner_rule_denies_anonymous_and_ownerless_pastes(): void
    {
        $owned = PasteFactory::owned(SecurityFixtures::user()->actorId());

        self::assertSame(self::DENIED, $this->vote(PasteDeleteVoter::OWNER, null, $owned));
        self::assertSame(self::DENIED, $this->vote(PasteDeleteVoter::OWNER, SecurityFixtures::user(), PasteFactory::anonymous()));
    }

    public function test_moderator_rule_grants_the_permission_holder_on_any_paste(): void
    {
        $moderator = SecurityFixtures::user([PastePermissions::DELETE_ANY]);

        self::assertSame(self::GRANTED, $this->vote(PasteDeleteVoter::MODERATOR, $moderator, PasteFactory::anonymous()));
        self::assertSame(self::GRANTED, $this->vote(PasteDeleteVoter::MODERATOR, $moderator, PasteFactory::owned(SecurityFixtures::user()->actorId())));
    }

    public function test_moderator_rule_denies_users_without_the_permission_even_if_they_own_the_paste(): void
    {
        $owner = SecurityFixtures::user([PastePermissions::CREATE]);

        self::assertSame(self::DENIED, $this->vote(PasteDeleteVoter::MODERATOR, $owner, PasteFactory::owned($owner->actorId())));
    }

    public function test_moderator_rule_denies_anonymous(): void
    {
        self::assertSame(self::DENIED, $this->vote(PasteDeleteVoter::MODERATOR, null, PasteFactory::anonymous()));
    }

    public function test_token_rule_grants_a_valid_token_without_any_session(): void
    {
        $token = DeleteToken::generate();

        self::assertSame(self::GRANTED, $this->vote(PasteDeleteVoter::TOKEN, null, PasteFactory::anonymous($token), $token));
    }

    public function test_token_rule_denies_wrong_or_missing_tokens(): void
    {
        $paste = PasteFactory::anonymous(DeleteToken::generate());

        self::assertSame(self::DENIED, $this->vote(PasteDeleteVoter::TOKEN, null, $paste, DeleteToken::generate()));
        self::assertSame(self::DENIED, $this->vote(PasteDeleteVoter::TOKEN, null, $paste, null));
    }

    public function test_token_rule_does_not_apply_to_owned_pastes(): void
    {
        $owned = PasteFactory::owned(SecurityFixtures::user()->actorId());

        self::assertSame(self::DENIED, $this->vote(PasteDeleteVoter::TOKEN, null, $owned, DeleteToken::generate()));
    }

    public function test_token_rule_ignores_the_users_permissions(): void
    {
        $moderator = SecurityFixtures::user([PastePermissions::DELETE_ANY]);

        self::assertSame(self::DENIED, $this->vote(PasteDeleteVoter::TOKEN, $moderator, PasteFactory::anonymous(), null));
    }

    public function test_it_abstains_on_unknown_attributes_and_subjects(): void
    {
        $request = new PasteDeletionRequest(PasteFactory::anonymous(), null);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote(new NullToken(), $request, ['OTRA_COSA']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote(new NullToken(), new \stdClass(), [PasteDeleteVoter::OWNER]));
    }

    private function vote(string $attribute, ?SecurityUser $user, Paste $paste, ?DeleteToken $token = null): int
    {
        $symfonyToken = null === $user ? new NullToken() : SecurityFixtures::token($user);

        return $this->voter->vote($symfonyToken, new PasteDeletionRequest($paste, $token), [$attribute]);
    }
}