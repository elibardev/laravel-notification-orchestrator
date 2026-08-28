<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Support;

use Elibardev\NotificationOrchestrator\Contracts\IdGenerator;
use Illuminate\Support\Str;

final class UuidGenerator implements IdGenerator
{
    public function generate(): string
    {
        return (string) Str::uuid();
    }
}
