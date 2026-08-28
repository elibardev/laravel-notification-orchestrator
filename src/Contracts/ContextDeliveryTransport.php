<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\DeliveryResult;
use Elibardev\NotificationOrchestrator\Context\ContextDeliveryPlan;

interface ContextDeliveryTransport
{
    public function name(): string;

    public function validateConfiguration(): void;

    public function health(): ChannelHealth;

    public function publish(ContextDeliveryPlan $delivery): DeliveryResult;
}
