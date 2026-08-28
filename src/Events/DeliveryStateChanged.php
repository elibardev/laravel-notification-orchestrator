<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Events;

use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;

final readonly class DeliveryStateChanged
{
    public function __construct(public string $deliveryId, public string $notificationId, public string $correlationId, public string $channel, public DeliveryStatus $status) {}
}
