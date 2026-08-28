<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator;

enum NotificationSeverity: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
}
