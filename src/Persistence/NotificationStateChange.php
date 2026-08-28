<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Persistence;

final readonly class NotificationStateChange
{
    public function __construct(public StoredNotification $notification, public bool $changed, public int $unreadCount) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['notification_id' => $this->notification->id, 'state' => $this->notification->state(), 'meta' => ['unread_count' => $this->unreadCount]];
    }
}
