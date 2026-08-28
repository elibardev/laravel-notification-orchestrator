<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Execution;

use Elibardev\NotificationOrchestrator\Planning\ChannelPlan;
use Elibardev\NotificationOrchestrator\Planning\DeliveryPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class DeliverNotification implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function __construct(public readonly DeliveryPlan $plan, public readonly ChannelPlan $channelPlan, public int $tries = 3, public int $backoff = 5)
    {
        $this->onConnection($channelPlan->queueConnection);
        $this->onQueue($channelPlan->queue);
    }

    public function handle(ChannelRunner $runner): void
    {
        $runner->run($this->plan, $this->channelPlan);
    }
}
