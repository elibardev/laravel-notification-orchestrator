<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\Planning\NotificationDispatchPlan;

interface DeliveryExecutor
{
    public function execute(NotificationDispatchPlan $plan): void;
}
