<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Fixtures;

use Elibardev\NotificationOrchestrator\Contracts\PresencePolicy;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;

final class ActiveContextPolicy implements PresencePolicy
{
    public bool $active = true;

    public function suppress(RecipientIdentity $recipient, NotificationContext $context, string $channel): bool
    {
        return $this->active;
    }
}
