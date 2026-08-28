<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Planning;

use Elibardev\NotificationOrchestrator\Channels\ChannelDestination;
use Elibardev\NotificationOrchestrator\Channels\ChannelPlanStatus;
use Elibardev\NotificationOrchestrator\Channels\SkipReason;

final readonly class ChannelPlan
{
    /** @param list<ChannelDestination> $destinations */
    public function __construct(public string $channel, public ChannelPlanStatus $status, public array $destinations = [],
        public ?SkipReason $skipReason = null, public bool $queued = false, public ?string $queueConnection = null, public ?string $queue = null) {}
}
