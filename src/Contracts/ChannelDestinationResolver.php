<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\Channels\ChannelDestination;
use Elibardev\NotificationOrchestrator\NotificationContext;

interface ChannelDestinationResolver
{
    /** @return iterable<ChannelDestination> */
    public function resolve(object $recipient, NotificationContext $context): iterable;
}
