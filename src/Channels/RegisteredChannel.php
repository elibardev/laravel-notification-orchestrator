<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

use Elibardev\NotificationOrchestrator\Contracts\ChannelDestinationResolver;
use Elibardev\NotificationOrchestrator\Contracts\NotificationChannel;
use Illuminate\Contracts\Container\Container;

final readonly class RegisteredChannel
{
    /** @param class-string<NotificationChannel>|NotificationChannel|null $implementation
     * @param  class-string<ChannelDestinationResolver>|ChannelDestinationResolver|null  $destinationResolver
     */
    public function __construct(public ChannelDefinition $definition, private string|NotificationChannel|null $implementation,
        private string|ChannelDestinationResolver|null $destinationResolver, private Container $container) {}

    public function channel(): ?NotificationChannel
    {
        return is_string($this->implementation) ? $this->container->make($this->implementation) : $this->implementation;
    }

    public function resolver(): ?ChannelDestinationResolver
    {
        return is_string($this->destinationResolver) ? $this->container->make($this->destinationResolver) : $this->destinationResolver;
    }

    public function implemented(): bool
    {
        return $this->implementation !== null;
    }
}
