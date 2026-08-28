<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Planning;

use Elibardev\NotificationOrchestrator\Channels\ChannelPlanStatus;
use Elibardev\NotificationOrchestrator\Context\ContextDeliveryPlan;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\NotificationDispatchResult;
use Elibardev\NotificationOrchestrator\NotificationPayload;

final readonly class NotificationDispatchPlan
{
    private NotificationDispatchResult $dispatchResult;

    /** @param list<DeliveryPlan> $recipients
     * @param  list<ContextDeliveryPlan>  $contexts
     */
    public function __construct(public NotificationContext $context, public NotificationPayload $payload, public string $correlationId,
        public array $recipients, public array $contexts)
    {
        $jobs = 0;
        foreach ($this->recipients as $recipient) {
            foreach ($recipient->channels as $channel) {
                if ($channel->status === ChannelPlanStatus::DELIVER && $channel->queued) {
                    $jobs++;
                }
            }
        }
        $this->dispatchResult = new NotificationDispatchResult($this->payload->id, $this->correlationId, count($this->recipients), $jobs, count($this->contexts));
    }

    public function result(): NotificationDispatchResult
    {
        return $this->dispatchResult;
    }
}
