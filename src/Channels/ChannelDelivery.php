<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

use Elibardev\NotificationOrchestrator\Planning\ChannelPlan;
use Elibardev\NotificationOrchestrator\Planning\DeliveryPlan;

final readonly class ChannelDelivery
{
    public function __construct(public DeliveryPlan $recipientPlan, public ChannelPlan $channelPlan) {}
}
