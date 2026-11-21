<?php

declare(strict_types=1);

namespace App\Tests\Paste\Domain;

use App\Paste\Domain\DeletionReason;
use App\Paste\Domain\Event\PasteBurned;
use App\Paste\Domain\Event\PasteDeleted;
use App\Paste\Domain\Event\PasteDeletedByModerator;
use App\Paste\Domain\ValueObject\ViewPolicy;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\UserId;
use App\Tests\Paste\Support\PasteFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeletionAuditabilityTest extends TestCase
{
    #[DataProvider('destructionPaths')]
    public function test_every_destruction_path_emits_exactly_one_auditable_event(DeletionReason $expected, \Closure $destroy): void
    {
        $events = $destroy();

        $reasons = array_values(array_filter(array_map(self::reasonOf(...), $events)));

        self::assertSame([$expected], $reasons);
    }

    public function test_the_provider_covers_every_deletion_reason(): void
    {
        $covered = array_map(static fn (array $case): string => $case[0]->value, array_values(iterator_to_array(self::destructionPaths())));
        $all = array_map(static fn (DeletionReason $r): string => $r->value, DeletionReason::cases());
        sort($covered);
        sort($all);

        self::assertSame($all, $covered);
    }

    public static function destructionPaths(): iterable
    {
        $now = new \DateTimeImmutable('2026-10-13 12:00:00');
        $actor = UserId::generate();

        yield 'owner' => [DeletionReason::Owner, static function () use ($now, $actor): array {
            $paste = PasteFactory::owned($actor);
            $paste->pullDomainEvents();
            $paste->destroy(DeletionReason::Owner, $now, $actor);

            return $paste->pullDomainEvents();
        }];

        yield 'moderator' => [DeletionReason::Moderator, static function () use ($now, $actor): array {
            $paste = PasteFactory::anonymous();
            $paste->pullDomainEvents();
            $paste->destroy(DeletionReason::Moderator, $now, $actor);

            return $paste->pullDomainEvents();
        }];

        yield 'token' => [DeletionReason::Token, static function () use ($now): array {
            $paste = PasteFactory::anonymous();
            $paste->pullDomainEvents();
            $paste->destroy(DeletionReason::Token, $now);

            return $paste->pullDomainEvents();
        }];

        yield 'expired' => [DeletionReason::Expired, static function () use ($now): array {
            $paste = PasteFactory::anonymous();
            $paste->pullDomainEvents();
            $paste->destroy(DeletionReason::Expired, $now);

            return $paste->pullDomainEvents();
        }];

        yield 'burned' => [DeletionReason::Burned, static function () use ($now): array {
            $paste = PasteFactory::anonymous(views: ViewPolicy::create(true, null));
            $paste->pullDomainEvents();
            $paste->view($now);

            return $paste->pullDomainEvents();
        }];
    }

    private static function reasonOf(DomainEvent $event): ?DeletionReason
    {
        return match (true) {
            $event instanceof PasteDeleted => $event->reason,
            $event instanceof PasteDeletedByModerator => DeletionReason::Moderator,
            $event instanceof PasteBurned => DeletionReason::Burned,
            default => null,
        };
    }
}