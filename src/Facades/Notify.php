<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Facades;

use Elibardev\NotificationOrchestrator\NotificationOrchestrator;
use Elibardev\NotificationOrchestrator\Testing\NotificationFake;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin NotificationOrchestrator
 * @mixin NotificationFake
 */
class Notify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NotificationOrchestrator::class;
    }
}
