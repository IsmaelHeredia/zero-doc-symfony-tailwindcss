<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidRoleData;

final readonly class PermissionCode implements \Stringable
{
    public const PASTE_CREATE = 'paste.create';
    public const PASTE_CREATE_PERMANENT = 'paste.create_permanent';
    public const PASTE_LARGE = 'paste.large';
    public const PASTE_DELETE_ANY = 'paste.delete_any';
    public const PASTE_LIST_OWN = 'paste.list_own';
    public const USER_MANAGE = 'user.manage';
    public const ROLE_MANAGE = 'role.manage';
    public const TAG_MANAGE_OWN = 'tag.manage_own';
    public const REPORT_REVIEW = 'report.review';
    public const AUDIT_READ = 'audit.read';

    private function __construct(public string $value)
    {
    }

    public static function catalog(): array
    {
        return [
            self::PASTE_CREATE,
            self::PASTE_CREATE_PERMANENT,
            self::PASTE_LARGE,
            self::PASTE_DELETE_ANY,
            self::PASTE_LIST_OWN,
            self::TAG_MANAGE_OWN,
            self::REPORT_REVIEW,
            self::USER_MANAGE,
            self::ROLE_MANAGE,
            self::AUDIT_READ,
        ];
    }

    public static function fromCatalog(string $value): self
    {
        $code = self::fromString($value);
        if (!\in_array($code->value, self::catalog(), true)) {
            throw InvalidRoleData::because('Permiso desconocido: no pertenece al catálogo.');
        }

        return $code;
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        if (strlen($normalized) > 64 || 1 !== preg_match('/^[a-z]+(\.[a-z_]+)+$/', $normalized)) {
            throw InvalidRoleData::because('Código de permiso inválido (formato: "recurso.accion").');
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}