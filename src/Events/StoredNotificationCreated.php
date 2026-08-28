<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Events;

use Elibardev\NotificationOrchestrator\Persistence\StoredNotification;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;

final readonly class StoredNotificationCreated
{
    public function __construct(public RecipientIdentity $recipient, public StoredNotification $notification, public string $correlationId) {}
}
