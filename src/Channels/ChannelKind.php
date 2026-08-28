<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Channels;

enum ChannelKind: string
{
    case STRUCTURAL = 'structural';
    case OPTIONAL = 'optional';
}
