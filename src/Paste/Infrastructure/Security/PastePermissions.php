<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Security;

final class PastePermissions
{
    public const CREATE = 'paste.create';
    public const CREATE_PERMANENT = 'paste.create_permanent';
    public const LARGE = 'paste.large';
    public const DELETE_ANY = 'paste.delete_any';
    public const LIST_OWN = 'paste.list_own';
    public const AUDIT_READ = 'audit.read';
}