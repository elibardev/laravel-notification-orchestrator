<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Context;

use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\DeliveryResult;
use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;
use Elibardev\NotificationOrchestrator\Contracts\ContextDeliveryTransport;
use Elibardev\NotificationOrchestrator\Contracts\MqttDriver;

final class MqttContextTransport implements ContextDeliveryTransport
{
    public function __construct(private MqttDriver $driver) {}

    public function name(): string
    {
        return 'mqtt';
    }

    public function validateConfiguration(): void
    {
        $this->driver->validateConfiguration();
    }

    public function health(): ChannelHealth
    {
        return $this->driver->health();
    }

    public function publish(ContextDeliveryPlan $delivery): DeliveryResult
    {
        $this->driver->publish($delivery->destination, json_encode($delivery->payload, JSON_THROW_ON_ERROR), $delivery->options['qos'] ?? 1, $delivery->options['retain'] ?? false);

        return new DeliveryResult('mqtt', DeliveryStatus::SENT, 'mqtt');
    }
}
