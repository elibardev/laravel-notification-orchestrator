<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

enum SkipReason: string
{
    case PRESENCE = 'presence';
    case NOT_REQUESTED = 'not_requested';
    case DISABLED = 'disabled';
    case USER_PREFERENCE = 'user_preference';
    case NO_DESTINATION = 'no_destination';
}
