<?php

declare(strict_types=1);

namespace App\Moderation\Application\File;

final readonly class FileAbuseReportCommand
{
    public function __construct(public string $pasteId, public string $reason, public ?string $comment)
    {
    }
}