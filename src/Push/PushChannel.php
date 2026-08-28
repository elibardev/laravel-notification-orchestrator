<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Push;

use Elibardev\NotificationOrchestrator\Channels\ChannelDelivery;
use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\DeliveryResult;
use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\NotificationChannel;
use Elibardev\NotificationOrchestrator\Devices\DatabasePushDestinationResolver;
use Elibardev\NotificationOrchestrator\Devices\DeviceRepository;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Illuminate\Contracts\Container\Container;

final class PushChannel implements NotificationChannel
{
    public function __construct(private PushDriverRegistry $drivers, private Configuration $config, private Container $container) {}

    public function name(): string
    {
        return 'push';
    }

    public function validateConfiguration(): void
    {
        if ($this->config->get('push.destination_resolver') === DatabasePushDestinationResolver::class && ! $this->config->enabled('devices')) {
            throw new ChannelConfigurationException('Managed push destinations require features.devices or an external resolver.');
        }
        $this->drivers->get($this->config->get('push.default_driver'))->validateConfiguration();
    }

    public function health(): ChannelHealth
    {
        return $this->drivers->get($this->config->get('push.default_driver'))->health();
    }

    public function send(ChannelDelivery $delivery): DeliveryResult
    {
        $channel = $delivery->channelPlan->destinations[0];
        $destination = new PushDestination($channel->value, $channel->metadata['driver'], $channel->metadata['platform'] ?? null, $channel->metadata['device_id'] ?? null);
        $driver = $this->drivers->get($destination->driver);
        $send = fn () => $driver->send($destination, new PushMessage($delivery->recipientPlan->payload, $delivery->recipientPlan->storedNotificationId));
        $result = $destination->deviceId !== null && $this->config->enabled('devices')
            ? $this->container->make(DeviceRepository::class)->deliverCurrent($delivery->recipientPlan->recipient, $destination, $send) : $send();

        return new DeliveryResult('push', $result->accepted ? DeliveryStatus::SENT : DeliveryStatus::FAILED, $destination->driver, $result->providerReference);
    }
}
