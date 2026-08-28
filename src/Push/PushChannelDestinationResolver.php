<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Push;

use Elibardev\NotificationOrchestrator\Channels\ChannelDestination;
use Elibardev\NotificationOrchestrator\Contracts\ChannelDestinationResolver;
use Elibardev\NotificationOrchestrator\Contracts\PushDestinationResolver;
use Elibardev\NotificationOrchestrator\NotificationContext;

final class PushChannelDestinationResolver implements ChannelDestinationResolver
{
    public function __construct(private PushDestinationResolver $resolver, private PushDriverRegistry $drivers) {}

    public function resolve(object $recipient, NotificationContext $context): iterable
    {
        foreach ($this->resolver->resolve($recipient, $context) as $destination) {
            $this->drivers->get($destination->driver)->validateConfiguration();
            yield new ChannelDestination($destination->token, ['driver' => $destination->driver, 'platform' => $destination->platform, 'device_id' => $destination->deviceId]);
        }
    }
}
