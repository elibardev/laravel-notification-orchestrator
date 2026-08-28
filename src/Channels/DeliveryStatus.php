<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

enum DeliveryStatus: string
{
    case PLANNED = 'planned';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
}
