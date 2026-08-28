<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Persistence;

final readonly class BulkNotificationStateChange
{
    public function __construct(public int $changed, public int $unreadCount, public string $readAt) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['changed' => $this->changed, 'state' => ['read_at' => $this->readAt], 'meta' => ['unread_count' => $this->unreadCount]];
    }
}
