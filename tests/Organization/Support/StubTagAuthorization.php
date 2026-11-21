<?php

declare(strict_types=1);

namespace App\Tests\Organization\Support;

use App\Organization\Application\Exception\TagActionNotAllowed;
use App\Organization\Application\Port\TagAuthorization;
use App\Shared\Domain\UserId;

final class StubTagAuthorization implements TagAuthorization
{
    public function __construct(private readonly ?UserId $actor)
    {
    }

    public function requireManage(): UserId
    {
        return $this->actor ?? throw TagActionNotAllowed::create();
    }

    public function requireListOwnPastes(): UserId
    {
        return $this->actor ?? throw TagActionNotAllowed::create();
    }
}