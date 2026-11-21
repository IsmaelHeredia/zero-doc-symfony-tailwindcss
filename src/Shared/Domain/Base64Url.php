<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final class Base64Url
{
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $value): ?string
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            return null;
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return false === $decoded ? null : $decoded;
    }
}