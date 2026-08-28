<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Fixtures;

final class UnusableChannel extends TestChannel
{
    public function __construct()
    {
        throw new \RuntimeException('Disabled provider must never be instantiated.');
    }
}
