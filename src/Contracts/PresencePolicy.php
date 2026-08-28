<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Contracts;

use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;

interface PresencePolicy
{
    public function suppress(RecipientIdentity $recipient, NotificationContext $context, string $channel): bool;
}
