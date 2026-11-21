<?php

declare(strict_types=1);

namespace App\Tests\Paste\Support;

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

final class PasteFactory
{
    public static function anonymous(?DeleteToken $token = null, ?ViewPolicy $views = null): Paste
    {
        return Paste::createAnonymous(
            PasteId::generate(),
            self::ciphertext(),
            self::iv(),
            ExpirationPolicy::create(3600, self::now(), 7 * 86400),
            $views ?? ViewPolicy::create(false, null),
            ContentSizeLimit::ofBytes(1_000_000),
            ($token ?? DeleteToken::generate())->hash(),
            self::now(),
        );
    }

    public static function expired(): Paste
    {
        $past = new \DateTimeImmutable('2020-01-01 00:00:00');

        return Paste::createAnonymous(
            PasteId::generate(), self::ciphertext(), self::iv(),
            ExpirationPolicy::create(60, $past, 3600),
            ViewPolicy::create(false, null),
            ContentSizeLimit::ofBytes(1_000_000),
            DeleteToken::generate()->hash(),
            $past,
        );
    }

    public static function alive(): Paste
    {
        $now = new \DateTimeImmutable();

        return Paste::createAnonymous(
            PasteId::generate(), self::ciphertext(), self::iv(),
            ExpirationPolicy::create(3600, $now, 7 * 86400),
            ViewPolicy::create(false, null),
            ContentSizeLimit::ofBytes(1_000_000),
            DeleteToken::generate()->hash(),
            $now,
        );
    }

    public static function owned(UserId $owner): Paste
    {
        return Paste::createOwned(
            PasteId::generate(),
            self::ciphertext(),
            self::iv(),
            ExpirationPolicy::create(null, self::now(), null),
            ViewPolicy::create(false, null),
            ContentSizeLimit::ofBytes(1_000_000),
            $owner,
            self::now(),
        );
    }

    private static function ciphertext(): Ciphertext
    {
        return Ciphertext::fromBase64Url(Base64Url::encode(random_bytes(64)));
    }

    private static function iv(): InitializationVector
    {
        return InitializationVector::fromBase64Url(Base64Url::encode(random_bytes(12)));
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-13 12:00:00');
    }
}