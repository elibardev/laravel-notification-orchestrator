<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\Channels\ChannelDelivery;
use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\DeliveryResult;

interface NotificationChannel
{
    public function name(): string;

    public function validateConfiguration(): void;

    public function health(): ChannelHealth;

    public function send(ChannelDelivery $delivery): DeliveryResult;
}
