<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Mqtt;

use Elibardev\NotificationOrchestrator\Channels\ChannelDelivery;
use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\DeliveryResult;
use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\MqttDriver;
use Elibardev\NotificationOrchestrator\Contracts\NotificationChannel;

final class MqttChannel implements NotificationChannel
{
    public function __construct(private MqttDriver $driver, private Configuration $config) {}

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

    public function send(ChannelDelivery $delivery): DeliveryResult
    {
        $this->driver->publish($delivery->channelPlan->destinations[0]->value, json_encode($delivery->recipientPlan->payload, JSON_THROW_ON_ERROR),
            $this->config->get('mqtt.qos', 1), $this->config->get('mqtt.retain', false));

        return new DeliveryResult('mqtt', DeliveryStatus::SENT, 'mqtt');
    }
}
