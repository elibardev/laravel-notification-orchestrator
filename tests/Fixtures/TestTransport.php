<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Fixtures;

use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\DeliveryResult;
use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;
use Elibardev\NotificationOrchestrator\Channels\HealthStatus;
use Elibardev\NotificationOrchestrator\Context\ContextDeliveryPlan;
use Elibardev\NotificationOrchestrator\Contracts\ContextDeliveryTransport;

class TestTransport implements ContextDeliveryTransport
{
    public int $published = 0;

    public function name(): string
    {
        return 'custom';
    }

    public function validateConfiguration(): void {}

    public function health(): ChannelHealth
    {
        return new ChannelHealth(HealthStatus::HEALTHY);
    }

    public function publish(ContextDeliveryPlan $delivery): DeliveryResult
    {
        $this->published++;

        return new DeliveryResult('custom', DeliveryStatus::SENT);
    }
}
