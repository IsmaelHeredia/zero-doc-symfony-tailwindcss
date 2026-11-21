<?php

declare(strict_types=1);

namespace App\Organization\Application\Query;

use App\Organization\Application\Port\TagAuthorization;

final readonly class ListMyTags
{
    public function __construct(private TagAuthorization $authorization, private TagReader $reader)
    {
    }

    public function __invoke(): array
    {
        return $this->reader->tagsOf($this->authorization->requireManage());
    }
}