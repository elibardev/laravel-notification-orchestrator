<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use DateTimeInterface;
use Elibardev\NotificationOrchestrator\Persistence\BulkNotificationStateChange;
use Elibardev\NotificationOrchestrator\Persistence\NotificationPage;
use Elibardev\NotificationOrchestrator\Persistence\NotificationQuery;
use Elibardev\NotificationOrchestrator\Persistence\NotificationStateChange;
use Elibardev\NotificationOrchestrator\Persistence\StoredNotification;
use Elibardev\NotificationOrchestrator\Planning\DeliveryPlan;

interface NotificationRepository
{
    public function store(DeliveryPlan $plan): StoredNotification;

    public function paginateFor(object $notifiable, NotificationQuery $query): NotificationPage;

    public function findFor(object $notifiable, string $storedNotificationId): ?StoredNotification;

    public function unreadCount(object $notifiable): int;

    public function markRead(object $notifiable, string $storedNotificationId, ?DateTimeInterface $at = null): NotificationStateChange;

    public function markUnread(object $notifiable, string $storedNotificationId): NotificationStateChange;

    public function markAllRead(object $notifiable, ?DateTimeInterface $at = null): BulkNotificationStateChange;
}
