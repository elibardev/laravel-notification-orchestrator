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

final class DeliveryChannel implements ChannelDestinationResolver, NotificationChannel
{
    /** @var list<string> */
    public array $calls = [];

    public bool $fail = true;

    public function name(): string
    {
        return 'test';
    }

    public function validateConfiguration(): void {}

    public function health(): ChannelHealth
    {
        return new ChannelHealth(HealthStatus::HEALTHY);
    }

    public function resolve(object $recipient, NotificationContext $context): iterable
    {
        return [new ChannelDestination('safe'), new ChannelDestination('secret-token')];
    }

    public function send(ChannelDelivery $delivery): DeliveryResult
    {
        $value = $delivery->channelPlan->destinations[0]->value;
        $this->calls[] = $value;
        if ($this->fail && $value === 'secret-token') {
            throw new \RuntimeException('SECRET-RAW-PROVIDER-PASSWORD');
        }

        return new DeliveryResult('test', DeliveryStatus::SENT, 'fake', 'accepted');
    }
}
