<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Testing;

use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\NotificationDispatchResult;
use Elibardev\NotificationOrchestrator\NotificationPayload;
use Elibardev\NotificationOrchestrator\Planning\NotificationDispatchPlan;

final readonly class RecordedNotification
{
    public function __construct(public NotificationContext $context, public NotificationPayload $payload,
        public NotificationDispatchPlan $plan, public NotificationDispatchResult $result) {}
}
