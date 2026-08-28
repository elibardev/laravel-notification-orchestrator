<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Planning;

use Elibardev\NotificationOrchestrator\NotificationPayload;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;

final readonly class DeliveryPlan
{
    /** @param list<ChannelPlan> $channels */
    public function __construct(public RecipientIdentity $recipient, public NotificationPayload $payload,
        public ?string $storedNotificationId, public array $channels, public string $correlationId) {}
}
