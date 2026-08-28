<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

enum ChannelPlanStatus: string
{
    case DELIVER = 'deliver';
    case SKIP = 'skip';
}
