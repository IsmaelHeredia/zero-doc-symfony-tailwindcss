<?php

declare(strict_types=1);

namespace App\Tests\Moderation\Domain;

use App\Moderation\Domain\Exception\InvalidReportData;
use App\Moderation\Domain\ValueObject\ReportComment;
use App\Shared\Domain\Base64Url;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportCommentTest extends TestCase
{
    public function test_it_normalizes_whitespace_into_a_single_line(): void
    {
        self::assertSame('hola mundo y más', ReportComment::fromString("  hola\n\tmundo   y más ")->value);
    }

    public function test_blank_means_no_comment(): void
    {
        self::assertNull(ReportComment::fromNullable(null));
        self::assertNull(ReportComment::fromNullable("  \n "));
    }

    public function test_it_accepts_exactly_the_maximum_length_but_not_more(): void
    {
        self::assertSame(280, mb_strlen(ReportComment::fromString(str_repeat('á ', 140))->value) + 1);

        $this->expectException(InvalidReportData::class);
        ReportComment::fromString(str_repeat('a', 281));
    }

    public function test_short_hashtags_are_fine(): void
    {
        self::assertSame('es #spam claramente', ReportComment::fromString('es #spam claramente')->value);
    }

    #[DataProvider('secretLooking')]
    public function test_it_rejects_anything_that_could_carry_a_key(string $comment): void
    {
        $this->expectException(InvalidReportData::class);
        ReportComment::fromString($comment);
    }

    public static function secretLooking(): iterable
    {
        $key = Base64Url::encode(random_bytes(32));
        $id = Base64Url::encode(random_bytes(16));

        yield 'enlace completo' => ["mirad https://sitio/p/$id#$key"];
        yield 'solo el fragmento' => ["#$key"];
        yield 'clave suelta' => ["la clave es $key"];
        yield 'fragmento largo' => ['#'.str_repeat('a', 16)];
    }

    #[DataProvider('malformed')]
    public function test_it_rejects_malformed_text(string $comment): void
    {
        $this->expectException(InvalidReportData::class);
        ReportComment::fromString($comment);
    }

    public static function malformed(): iterable
    {
        yield 'vacío' => [''];
        yield 'utf8 inválido' => ["a\xB1\x31"];
        yield 'carácter de control' => ["hola\x07mundo"];
    }
}