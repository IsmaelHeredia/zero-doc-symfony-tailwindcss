<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use App\Identity\Domain\Exception\InvalidUserData;
use App\Identity\Domain\ValueObject\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function test_it_normalizes_case_and_whitespace(): void
    {
        $email = Email::fromString('  Ana@Example.COM ');

        self::assertSame('ana@example.com', $email->value);
        self::assertTrue($email->equals(Email::fromString('ANA@example.com')));
    }

    #[DataProvider('invalidEmails')]
    public function test_it_rejects_invalid_emails(string $value): void
    {
        $this->expectException(InvalidUserData::class);
        Email::fromString($value);
    }

    public static function invalidEmails(): iterable
    {
        yield 'vacío' => [''];
        yield 'sin arroba' => ['ana.example.com'];
        yield 'sin dominio' => ['ana@'];
        yield 'demasiado largo' => [str_repeat('a', 250).'@example.com'];
    }
}