<?php

declare(strict_types=1);

namespace App\Tests\Paste\Domain;

use App\Paste\Domain\Exception\InvalidPasteData;
use App\Paste\Domain\ValueObject\KeyDerivation;
use App\Shared\Domain\Base64Url;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KeyDerivationTest extends TestCase
{
    public function test_no_parameters_means_no_password(): void
    {
        self::assertNull(KeyDerivation::fromNullable(null, null));
    }

    public function test_it_accepts_the_range_limits(): void
    {
        $salt = Base64Url::encode(random_bytes(16));

        self::assertSame(600_000, KeyDerivation::fromNullable($salt, KeyDerivation::MIN_ITERATIONS)->iterations);
        self::assertSame(2_000_000, KeyDerivation::fromNullable($salt, KeyDerivation::MAX_ITERATIONS)->iterations);
    }

    public static function invalid(): iterable
    {
        $salt = Base64Url::encode(random_bytes(16));

        yield 'solo salt' => [$salt, null];
        yield 'solo iteraciones' => [null, 600_000];
        yield 'pocas iteraciones' => [$salt, 599_999];
        yield 'demasiadas iteraciones' => [$salt, 2_000_001];
        yield 'iteraciones negativas' => [$salt, -1];
        yield 'salt corto' => [Base64Url::encode(random_bytes(8)), 600_000];
        yield 'salt largo' => [Base64Url::encode(random_bytes(32)), 600_000];
        yield 'salt no base64url' => ['!!!!!!!!!!!!!!!!!!!!!!', 600_000];
    }

    #[DataProvider('invalid')]
    public function test_it_rejects_invalid_parameters(?string $salt, ?int $iterations): void
    {
        $this->expectException(InvalidPasteData::class);
        KeyDerivation::fromNullable($salt, $iterations);
    }
}