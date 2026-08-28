<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Push;

use Elibardev\NotificationOrchestrator\Contracts\PushDriver;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelAlreadyRegisteredException;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Elibardev\NotificationOrchestrator\Support\Values;
use Illuminate\Contracts\Container\Container;

final class PushDriverRegistry
{
    /** @var array<string,class-string<PushDriver>|PushDriver> */
    private array $drivers = [];

    public function __construct(private Container $container) {}

    /** @param class-string<PushDriver>|PushDriver $driver */
    public function register(string $name, string|PushDriver $driver): void
    {
        Values::name($name);
        if (isset($this->drivers[$name])) {
            throw new ChannelAlreadyRegisteredException('Push driver already registered.');
        }
        $this->drivers[$name] = $driver;
    }

    public function get(string $name): PushDriver
    {
        $driver = $this->drivers[$name] ?? throw new ChannelConfigurationException('Unknown push driver.');

        return is_string($driver) ? $this->container->make($driver) : $driver;
    }
}
