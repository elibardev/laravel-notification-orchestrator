<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\Push\PushDestination;

interface PushDestinationResolver
{
    /** @return iterable<PushDestination> */
    public function resolve(object $notifiable, NotificationContext $context): iterable;
}
