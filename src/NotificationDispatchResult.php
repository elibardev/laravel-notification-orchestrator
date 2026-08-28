<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator;

final readonly class NotificationDispatchResult
{
    public function __construct(public string $notificationId, public string $correlationId, public int $recipientCount,
        public int $plannedQueueJobCount, public int $contextDeliveryCount) {}
}
