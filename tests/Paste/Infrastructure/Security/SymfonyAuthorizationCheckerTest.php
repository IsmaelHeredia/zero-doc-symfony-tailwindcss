<?php

declare(strict_types=1);

namespace App\Tests\Paste\Infrastructure\Security;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\ValueObject\DeleteToken;
use App\Paste\Infrastructure\Security\PasteCreateVoter;
use App\Paste\Infrastructure\Security\PasteDeleteVoter;
use App\Paste\Infrastructure\Security\PastePermissions;
use App\Paste\Infrastructure\Security\SymfonyAuthorizationChecker;
use App\Tests\Paste\Support\PasteFactory;
use App\Tests\Paste\Support\SecurityFixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

final class SymfonyAuthorizationCheckerTest extends TestCase
{
    private TokenStorage $tokens;
    private SymfonyAuthorizationChecker $checker;

    protected function setUp(): void
    {
        $this->tokens = new TokenStorage();
        $this->checker = new SymfonyAuthorizationChecker(
            new AuthorizationChecker($this->tokens, new AccessDecisionManager([new PasteDeleteVoter(), new PasteCreateVoter()])),
            $this->tokens,
        );
    }

    public function test_owner_who_is_also_moderator_is_recorded_as_owner(): void
    {
        $user = SecurityFixtures::user([PastePermissions::DELETE_ANY]);
        $this->loginAs($user);

        $grant = $this->checker->deletionGrantFor(PasteFactory::owned($user->actorId()), null);

        self::assertSame(DeletionReason::Owner, $grant->reason);
        self::assertTrue($grant->actor->equals($user->actorId()));
    }

    public function test_moderation_grant_carries_the_actor_for_the_audit_event(): void
    {
        $moderator = SecurityFixtures::user([PastePermissions::DELETE_ANY]);
        $this->loginAs($moderator);

        $grant = $this->checker->deletionGrantFor(PasteFactory::anonymous(), null);

        self::assertSame(DeletionReason::Moderator, $grant->reason);
        self::assertTrue($grant->actor->equals($moderator->actorId()));
    }

    public function test_a_valid_token_works_without_session_and_has_no_actor(): void
    {
        $token = DeleteToken::generate();

        $grant = $this->checker->deletionGrantFor(PasteFactory::anonymous($token), $token);

        self::assertSame(DeletionReason::Token, $grant->reason);
        self::assertNull($grant->actor);
    }

    public function test_a_moderator_who_has_the_token_is_not_recorded_as_moderation(): void
    {
        $this->loginAs(SecurityFixtures::user([PastePermissions::DELETE_ANY]));
        $token = DeleteToken::generate();

        $grant = $this->checker->deletionGrantFor(PasteFactory::anonymous($token), $token);

        self::assertSame(DeletionReason::Token, $grant->reason);
    }

    public function test_it_denies_when_no_rule_applies(): void
    {
        $paste = PasteFactory::anonymous();
        self::assertNull($this->checker->deletionGrantFor($paste, null));                       
        self::assertNull($this->checker->deletionGrantFor($paste, DeleteToken::generate()));    

        $this->loginAs(SecurityFixtures::user([PastePermissions::CREATE]));                    
        self::assertNull($this->checker->deletionGrantFor($paste, null));
    }

    public function test_creation_rights_for_anonymous(): void
    {
        $rights = $this->checker->creationRights();

        self::assertNull($rights->actor);
        self::assertTrue($rights->canCreate);
        self::assertFalse($rights->canCreatePermanent);
        self::assertFalse($rights->canCreateLarge);
    }

    public function test_creation_rights_follow_the_users_permissions(): void
    {
        $user = SecurityFixtures::user([PastePermissions::CREATE, PastePermissions::LARGE]);
        $this->loginAs($user);

        $rights = $this->checker->creationRights();

        self::assertTrue($rights->actor->equals($user->actorId()));
        self::assertTrue($rights->canCreate);
        self::assertFalse($rights->canCreatePermanent);
        self::assertTrue($rights->canCreateLarge);
    }

    public function test_a_user_without_paste_create_has_no_creation_rights(): void
    {
        $this->loginAs(SecurityFixtures::user([]));

        self::assertFalse($this->checker->creationRights()->canCreate);
    }

    private function loginAs(SecurityUser $user): void
    {
        $this->tokens->setToken(SecurityFixtures::token($user));
    }
}