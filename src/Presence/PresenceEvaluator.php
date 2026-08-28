<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Presence;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\PresencePolicy;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Illuminate\Contracts\Container\Container;

final class PresenceEvaluator
{
    public function __construct(private Configuration $config, private Container $container) {}

    public function suppress(RecipientIdentity $recipient, NotificationContext $context, string $channel): bool
    {
        if (! $this->config->enabled('presence')) {
            return false;
        }
        $policy = $this->container->make($this->config->get('presence.policy'));
        if (! $policy instanceof PresencePolicy) {
            throw new \LogicException('Invalid presence policy.');
        }

        return $policy->suppress($recipient, $context, $channel);
    }
}
