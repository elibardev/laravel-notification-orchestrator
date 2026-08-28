<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

interface IdGenerator
{
    public function generate(): string;
}
