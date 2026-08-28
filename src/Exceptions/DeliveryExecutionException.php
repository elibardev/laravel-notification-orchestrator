<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Exceptions;

final class DeliveryExecutionException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Notification delivery execution failed. Consult sanitized tracking by correlation ID.');
    }
}
