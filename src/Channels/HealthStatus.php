<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

enum HealthStatus: string
{
    case HEALTHY = 'HEALTHY';
    case DEGRADED = 'DEGRADED';
    case INVALID = 'INVALID';
}
