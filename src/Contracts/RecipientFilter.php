<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\NotificationContext;

interface RecipientFilter
{
    /** @param iterable<object> $recipients
     * @return iterable<object>
     */
    public function filter(iterable $recipients, NotificationContext $context): iterable;
}
