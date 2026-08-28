<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Persistence;

final readonly class NotificationPage
{
    /** @param list<StoredNotification> $items */
    public function __construct(public array $items, public ?string $nextCursor, public int $unreadCount) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['notifications' => array_map(fn (StoredNotification $item) => $item->toArray(), $this->items), 'meta' => ['unread_count' => $this->unreadCount, 'next_cursor' => $this->nextCursor]];
    }
}
