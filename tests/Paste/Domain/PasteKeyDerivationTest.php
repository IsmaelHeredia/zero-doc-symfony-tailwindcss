<?php

declare(strict_types=1);

namespace App\Tests\Paste\Domain;

use App\Paste\Domain\Paste;
use App\Paste\Domain\ValueObject\Ciphertext;
use App\Paste\Domain\ValueObject\ContentSizeLimit;
use App\Paste\Domain\ValueObject\DeleteToken;
use App\Paste\Domain\ValueObject\ExpirationPolicy;
use App\Paste\Domain\ValueObject\InitializationVector;
use App\Paste\Domain\ValueObject\KeyDerivation;
use App\Paste\Domain\ValueObject\PasteId;
use App\Paste\Domain\ValueObject\ViewPolicy;
use App\Shared\Domain\Base64Url;
use App\Tests\Paste\Support\PasteFactory;
use PHPUnit\Framework\TestCase;

final class PasteKeyDerivationTest extends TestCase
{
    public function test_the_payload_carries_the_public_kdf_parameters(): void
    {
        $now = new \DateTimeImmutable('2026-10-13 12:00:00');
        $salt = Base64Url::encode(random_bytes(16));
        $paste = Paste::createAnonymous(
            PasteId::generate(),
            Ciphertext::fromBase64Url(Base64Url::encode(random_bytes(64))),
            InitializationVector::fromBase64Url(Base64Url::encode(random_bytes(12))),
            ExpirationPolicy::create(3600, $now, 7 * 86400),
            ViewPolicy::create(false, null),
            ContentSizeLimit::ofBytes(1_000_000),
            DeleteToken::generate()->hash(),
            $now,
            KeyDerivation::fromNullable($salt, 600_000),
        );

        $payload = $paste->view($now);

        self::assertSame($salt, $payload->keyDerivation->salt);
        self::assertSame(600_000, $payload->keyDerivation->iterations);
    }

    public function test_a_paste_without_password_has_no_kdf(): void
    {
        $payload = PasteFactory::anonymous()->view(new \DateTimeImmutable('2026-10-13 12:00:00'));

        self::assertNull($payload->keyDerivation);
    }
}