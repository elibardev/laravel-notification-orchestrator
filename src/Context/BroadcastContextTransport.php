<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Context;

use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\DeliveryResult;
use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;
use Elibardev\NotificationOrchestrator\Channels\PersonalBroadcastChannel;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\ContextDeliveryTransport;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PrivateChannel;

final class BroadcastContextTransport implements ContextDeliveryTransport
{
    public function __construct(private BroadcastManager $broadcast, private Configuration $config, private PersonalBroadcastChannel $personal) {}

    public function name(): string
    {
        return 'broadcast';
    }

    public function validateConfiguration(): void
    {
        $this->personal->validateConfiguration();
    }

    public function health(): ChannelHealth
    {
        return $this->personal->health();
    }

    public function publish(ContextDeliveryPlan $delivery): DeliveryResult
    {
        $this->broadcast->connection($this->config->get('broadcast.connection'))->broadcast([new PrivateChannel($delivery->destination)], 'notification.context', $delivery->payload->toArray());

        return new DeliveryResult('broadcast', DeliveryStatus::SENT, 'laravel');
    }
}
