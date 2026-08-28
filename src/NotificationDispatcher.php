<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator;

final class NotificationDispatcher
{
    public function __construct(private NotificationOrchestrator $orchestrator) {}

    public function dispatch(NotificationContext $context, mixed $recipients): NotificationDispatchResult
    {
        return $this->orchestrator->send($context, [$recipients]);
    }
}
