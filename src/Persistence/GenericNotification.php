<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Persistence;

use Elibardev\NotificationOrchestrator\NotificationPayload;
use Illuminate\Notifications\Notification;

final class GenericNotification extends Notification
{
    public function __construct(private readonly NotificationPayload $payload) {}

    /** @return array<string,mixed> */
    public function toDatabase(): array
    {
        return $this->payload->toArray();
    }
}
