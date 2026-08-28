<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Fixtures;

use Elibardev\NotificationOrchestrator\Channels\ChannelDelivery;
use Elibardev\NotificationOrchestrator\Channels\ChannelDestination;
use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\DeliveryResult;
use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;
use Elibardev\NotificationOrchestrator\Channels\HealthStatus;
use Elibardev\NotificationOrchestrator\Contracts\ChannelDestinationResolver;
use Elibardev\NotificationOrchestrator\Contracts\NotificationChannel;
use Elibardev\NotificationOrchestrator\NotificationContext;

class TestChannel implements ChannelDestinationResolver, NotificationChannel
{
    public int $sent = 0;

    public int $resolved = 0;

    public bool $valid = true;

    public HealthStatus $state = HealthStatus::HEALTHY;

    /** @var list<string> */
    public array $destinations = ['endpoint-a', 'endpoint-b'];

    public function __construct(private string $channelName = 'test') {}

    public function name(): string
    {
        return $this->channelName;
    }

    public function validateConfiguration(): void
    {
        if (! $this->valid) {
            throw new \RuntimeException('SECRET-CREDENTIAL');
        }
    }

    public function health(): ChannelHealth
    {
        return new ChannelHealth($this->state);
    }

    public function resolve(object $recipient, NotificationContext $context): iterable
    {
        $this->resolved++;

        return array_map(fn (string $value) => new ChannelDestination($value), $this->destinations);
    }

    public function send(ChannelDelivery $delivery): DeliveryResult
    {
        $this->sent++;

        return new DeliveryResult($this->name(), DeliveryStatus::SENT);
    }
}
