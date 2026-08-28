<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Fixtures;

use Elibardev\NotificationOrchestrator\Contracts\PushDestinationResolver;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\Push\PushDestination;

final class ExternalPushResolver implements PushDestinationResolver
{
    public function resolve(object $notifiable, NotificationContext $context): iterable
    {
        return [new PushDestination('external-token', 'fake')];
    }
}
