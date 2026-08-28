<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Context;

use Elibardev\NotificationOrchestrator\NotificationPayload;

final readonly class ContextDeliveryPlan
{
    /** @param array<array-key,mixed> $options */
    public function __construct(public string $transport, public string $destination, public NotificationPayload $payload,
        public array $options, public string $correlationId) {}
}
