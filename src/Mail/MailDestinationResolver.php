<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Mail;

use Elibardev\NotificationOrchestrator\Channels\ChannelDestination;
use Elibardev\NotificationOrchestrator\Contracts\ChannelDestinationResolver;
use Elibardev\NotificationOrchestrator\NotificationContext;

final class MailDestinationResolver implements ChannelDestinationResolver
{
    public function resolve(object $recipient, NotificationContext $context): iterable
    {
        if (! method_exists($recipient, 'routeNotificationFor')) {
            return [];
        }
        $route = $recipient->routeNotificationFor('mail', new MailRoutingNotification($context));
        $destinations = [];
        foreach (is_array($route) ? $route : [$route] as $key => $value) {
            $address = is_string($key) ? $key : $value;
            if (is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $destinations[] = new ChannelDestination($address);
            }
        }

        return $destinations;
    }
}
