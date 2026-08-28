<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Context;

use Elibardev\NotificationOrchestrator\Configuration\CapabilityRegistry;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\ContextDeliveryTransport;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelAlreadyRegisteredException;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelNotFoundException;
use Elibardev\NotificationOrchestrator\Support\Values;
use Illuminate\Contracts\Container\Container;

final class ContextTransportRegistry
{
    /** @var array<string,class-string<ContextDeliveryTransport>|ContextDeliveryTransport|null> */
    private array $transports = [];

    public function __construct(private Container $container, private Configuration $config, private CapabilityRegistry $capabilities) {}

    /** @param class-string<ContextDeliveryTransport>|ContextDeliveryTransport|null $implementation */
    public function register(string $name, string|ContextDeliveryTransport|null $implementation = null): void
    {
        Values::name($name);
        if ($this->has($name)) {
            throw new ChannelAlreadyRegisteredException('Context transport already registered.');
        }
        $this->transports[$name] = $implementation;
        if (! isset($this->capabilities->all()[$name])) {
            $this->capabilities->register($name, $implementation !== null);
        }
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->transports);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->transports);
    }

    public function get(string $name): ?ContextDeliveryTransport
    {
        if (! $this->has($name)) {
            throw new ChannelNotFoundException('Unknown context transport.');
        }
        $value = $this->transports[$name];

        return is_string($value) ? $this->container->make($value) : $value;
    }

    public function validateEnabled(bool $planningOnly = false): void
    {
        foreach ($this->names() as $name) {
            if (! $this->config->enabled($name)) {
                continue;
            }
            try {
                $transport = $this->get($name);
                if ($transport === null) {
                    continue;
                }
                if ($transport->name() !== $name) {
                    throw new \LogicException;
                }
                $transport->validateConfiguration();
            } catch (\Throwable) {
                throw new ChannelConfigurationException('Invalid or unimplemented context transport '.$name.'.');
            }
        }
    }
}
