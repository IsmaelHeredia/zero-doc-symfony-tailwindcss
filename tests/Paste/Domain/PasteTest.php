<?php

declare(strict_types=1);

namespace App\Tests\Paste\Domain;

use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\Event\PasteBurned;
use App\Paste\Domain\Event\PasteCreated;
use App\Paste\Domain\Event\PasteDeleted;
use App\Paste\Domain\Event\PasteDeletedByModerator;
use App\Paste\Domain\Event\PasteViewed;
use App\Paste\Domain\Exception\InvalidPasteData;
use App\Paste\Domain\Exception\PasteConsumed;
use App\Paste\Domain\Exception\PasteExpired;
use App\Paste\Domain\Paste;
use App\Paste\Domain\ValueObject\Ciphertext;
use App\Paste\Domain\ValueObject\ContentSizeLimit;
use App\Paste\Domain\ValueObject\DeleteToken;
use App\Paste\Domain\ValueObject\ExpirationPolicy;
use App\Paste\Domain\ValueObject\InitializationVector;
use App\Paste\Domain\ValueObject\PasteId;
use App\Paste\Domain\ValueObject\ViewPolicy;
use App\Shared\Domain\Base64Url;
use App\Shared\Domain\UserId;
use PHPUnit\Framework\TestCase;
use App\Tests\Paste\Support\PasteFactory;

final class PasteTest extends TestCase
{
    private DeleteToken $token;

    public function test_creating_an_anonymous_paste_records_created_event(): void
    {
        $paste = $this->anonymousPaste();

        $events = $paste->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PasteCreated::class, $events[0]);
        self::assertNull($events[0]->ownerId);
    }

    public function test_it_rejects_ciphertext_over_the_size_limit(): void
    {
        $this->expectException(InvalidPasteData::class);

        $this->anonymousPaste(maxBytes: 10);
    }

    public function test_viewing_counts_a_view_and_records_event(): void
    {
        $paste = $this->anonymousPaste();
        $paste->pullDomainEvents();

        $paste->view($this->now());

        self::assertSame(1, $paste->views());
        self::assertInstanceOf(PasteViewed::class, $paste->pullDomainEvents()[0]);
        self::assertFalse($paste->isConsumed());
    }

    public function test_burn_after_read_consumes_on_first_view(): void
    {
        $paste = $this->anonymousPaste(ViewPolicy::create(true, null));
        $paste->pullDomainEvents();

        $paste->view($this->now());

        $events = $paste->pullDomainEvents();
        self::assertInstanceOf(PasteViewed::class, $events[0]);
        self::assertInstanceOf(PasteBurned::class, $events[1]);
        self::assertTrue($paste->isConsumed());

        $this->expectException(PasteConsumed::class);
        $paste->view($this->now());
    }

    public function test_max_views_is_consumed_on_the_last_allowed_view(): void
    {
        $paste = $this->anonymousPaste(ViewPolicy::create(false, 2));

        $paste->view($this->now());
        self::assertFalse($paste->isConsumed());

        $paste->view($this->now());
        self::assertTrue($paste->isConsumed());
    }

    public function test_expired_paste_cannot_be_viewed(): void
    {
        $paste = $this->anonymousPaste(expiration: ExpirationPolicy::create(60, $this->now(), null));

        $this->expectException(PasteExpired::class);
        $paste->view($this->now()->modify('+61 seconds'));
    }

    public function test_delete_token_is_verified_against_its_hash(): void
    {
        $paste = $this->anonymousPaste();

        self::assertTrue($paste->acceptsDeleteToken($this->token));
        self::assertFalse($paste->acceptsDeleteToken(DeleteToken::generate()));
    }

    public function test_ownership_check(): void
    {
        $owner = UserId::generate();
        $paste = Paste::createOwned(
            PasteId::generate(), $this->ciphertext(), $this->iv(),
            ExpirationPolicy::create(null, $this->now(), null),
            ViewPolicy::create(false, null),
            ContentSizeLimit::ofBytes(1_000_000),
            $owner,
            $this->now(),
        );

        self::assertTrue($paste->isOwnedBy($owner));
        self::assertFalse($paste->isOwnedBy(UserId::generate()));
        self::assertFalse($paste->acceptsDeleteToken(DeleteToken::generate()));
    }

    public function test_moderator_deletion_requires_actor_and_emits_dedicated_event(): void
    {
        $paste = $this->anonymousPaste();
        $paste->pullDomainEvents();

        try {
            $paste->destroy(DeletionReason::Moderator, $this->now());
            self::fail('Debió exigir moderador.');
        } catch (InvalidPasteData) {
        }

        $moderator = UserId::generate();
        $paste->destroy(DeletionReason::Moderator, $this->now(), $moderator);

        $event = $paste->pullDomainEvents()[0];
        self::assertInstanceOf(PasteDeletedByModerator::class, $event);
        self::assertTrue($event->moderatorId->equals($moderator));
    }

    public function test_token_deletion_emits_generic_deleted_event(): void
    {
        $paste = $this->anonymousPaste();
        $paste->pullDomainEvents();

        $paste->destroy(DeletionReason::Token, $this->now());

        $event = $paste->pullDomainEvents()[0];
        self::assertInstanceOf(PasteDeleted::class, $event);
        self::assertSame(DeletionReason::Token, $event->reason);
    }

    public function test_anonymous_ttl_cap_is_enforced(): void
    {
        $week = 7 * 86400;

        $this->expectException(InvalidPasteData::class);
        ExpirationPolicy::create($week + 1, $this->now(), $week);
    }

    public function test_permanent_paste_is_rejected_when_a_cap_exists(): void
    {
        $this->expectException(InvalidPasteData::class);
        ExpirationPolicy::create(null, $this->now(), 7 * 86400);
    }

    private function anonymousPaste(
        ?ViewPolicy $views = null,
        ?ExpirationPolicy $expiration = null,
        int $maxBytes = 1_000_000,
    ): Paste {
        $this->token = DeleteToken::generate();

        return Paste::createAnonymous(
            PasteId::generate(),
            $this->ciphertext(),
            $this->iv(),
            $expiration ?? ExpirationPolicy::create(3600, $this->now(), 7 * 86400),
            $views ?? ViewPolicy::create(false, null),
            ContentSizeLimit::ofBytes($maxBytes),
            $this->token->hash(),
            $this->now(),
        );
    }

    private function ciphertext(): Ciphertext
    {
        return Ciphertext::fromBase64Url(Base64Url::encode(random_bytes(64)));
    }

    private function iv(): InitializationVector
    {
        return InitializationVector::fromBase64Url(Base64Url::encode(random_bytes(12)));
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-13 12:00:00');
    }

    public function test_owner_deletion_requires_actor_and_records_who(): void
    {
        $owner = UserId::generate();
        $paste = PasteFactory::owned($owner);
        $paste->pullDomainEvents();

        try {
            $paste->destroy(DeletionReason::Owner, $this->now());
            self::fail('Debió exigir el usuario.');
        } catch (InvalidPasteData) {
        }

        $paste->destroy(DeletionReason::Owner, $this->now(), $owner);

        $event = $paste->pullDomainEvents()[0];
        self::assertInstanceOf(PasteDeleted::class, $event);
        self::assertTrue($event->actorId->equals($owner));
    }

    public function test_ttl_has_an_absolute_ceiling_even_without_a_cap(): void
    {
        $this->expectException(InvalidPasteData::class);
        ExpirationPolicy::create(PHP_INT_MAX, $this->now(), null);
    }

}