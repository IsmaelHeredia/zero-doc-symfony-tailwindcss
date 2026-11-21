<?php

declare(strict_types=1);

namespace App\Paste\Domain\ValueObject;

use App\Paste\Domain\Exception\InvalidPasteData;

final readonly class ViewPolicy
{
    private function __construct(public bool $burnAfterRead, public ?int $maxViews)
    {
    }

    public static function create(bool $burnAfterRead, ?int $maxViews): self
    {
        if ($burnAfterRead && null !== $maxViews) {
            throw InvalidPasteData::because('burn_after_read y max_views son excluyentes.');
        }
        if (null !== $maxViews && $maxViews < 1) {
            throw InvalidPasteData::because('max_views debe ser >= 1.');
        }

        return new self($burnAfterRead, $maxViews);
    }

    public function isExhaustedAfter(int $views): bool
    {
        $limit = $this->burnAfterRead ? 1 : $this->maxViews;

        return null !== $limit && $views >= $limit;
    }
}