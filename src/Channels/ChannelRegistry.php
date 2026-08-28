<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

use Elibardev\NotificationOrchestrator\Configuration\CapabilityRegistry;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\ChannelDestinationResolver;
use Elibardev\NotificationOrchestrator\Contracts\NotificationChannel;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelAlreadyRegisteredException;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelNotFoundException;
use Illuminate\Contracts\Container\Container;

final class ChannelRegistry
{
    /** @var array<string,RegisteredChannel> */
    private array $channels = [];

    public function __construct(private Container $container, private Configuration $config, private CapabilityRegistry $capabilities) {}

    /** @param class-string<NotificationChannel>|NotificationChannel|null $implementation
     * @param  class-string<ChannelDestinationResolver>|ChannelDestinationResolver|null  $destinationResolver
     */
    public function register(ChannelDefinition $definition, string|NotificationChannel|null $implementation = null,
        string|ChannelDestinationResolver|null $destinationResolver = null): void
    {
        if ($this->has($definition->name)) {
            throw new ChannelAlreadyRegisteredException('Channel already registered.');
        }
        $this->channels[$definition->name] = new RegisteredChannel($definition, $implementation, $destinationResolver, $this->container);
        if (! isset($this->capabilities->all()[$definition->name])) {
            $this->capabilities->register($definition->name, $implementation !== null);
        }
    }

    public function has(string $name): bool
    {
        return isset($this->channels[$name]);
    }

    public function get(string $name): RegisteredChannel
    {
        return $this->channels[$name] ?? throw new ChannelNotFoundException('Unknown requested channel.');
    }

    /** @return array<string,RegisteredChannel> */
    public function all(): array
    {
        return $this->channels;
    }

    /** @return array<string,RegisteredChannel> */
    public function enabled(): array
    {
        return array_filter($this->channels, fn ($c) => $this->config->enabled($c->definition->name));
    }

    public function validateEnabled(bool $planningOnly = false): void
    {
        foreach ($this->enabled() as $channel) {
            if (! $channel->implemented()) {
                if ($planningOnly) {
                    continue;
                }
                throw new ChannelConfigurationException('Enabled channel '.$channel->definition->name.' has no delivery implementation.');
            }
            try {
                $implementation = $channel->channel();
                if ($implementation?->name() !== $channel->definition->name) {
                    throw new \LogicException;
                }
                $implementation->validateConfiguration();
            } catch (\Throwable) {
                throw new ChannelConfigurationException('Invalid configuration for channel '.$channel->definition->name.'.');
            }
        }
    }
}
