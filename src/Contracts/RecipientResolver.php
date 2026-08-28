<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\NotificationContext;

interface RecipientResolver
{
    /** @return iterable<object> */
    public function resolve(NotificationContext $context): iterable;
}
