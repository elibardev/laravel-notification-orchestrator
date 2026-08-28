<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Execution;

use Elibardev\NotificationOrchestrator\Channels\ChannelPlanStatus;
use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Configuration\RuntimeHealth;
use Elibardev\NotificationOrchestrator\Context\ContextTransportRegistry;
use Elibardev\NotificationOrchestrator\Contracts\DeliveryExecutor;
use Elibardev\NotificationOrchestrator\Exceptions\DeliveryExecutionException;
use Elibardev\NotificationOrchestrator\Planning\ChannelPlan;
use Elibardev\NotificationOrchestrator\Planning\DeliveryPlan;
use Elibardev\NotificationOrchestrator\Planning\NotificationDispatchPlan;
use Elibardev\NotificationOrchestrator\Tracking\DeliveryTrackingRepository;
use Illuminate\Contracts\Queue\Factory;
use Psr\Log\LoggerInterface;

final class NotificationExecutor implements DeliveryExecutor
{
    public function __construct(private ChannelRunner $runner, private DeliveryTrackingRepository $tracking, private Configuration $config,
        private Factory $queues, private ContextTransportRegistry $transports, private LoggerInterface $logger, private RuntimeHealth $health) {}

    public function execute(NotificationDispatchPlan $plan): void
    {
        $this->health->validate();
        // Persist every inbox row before exposing any allocated personal ID.
        foreach ($plan->recipients as $recipient) {
            foreach ($recipient->channels as $channel) {
                if ($channel->channel === 'database' && $channel->status === ChannelPlanStatus::DELIVER) {
                    $this->runner->run($recipient, $channel);
                }
            }
        }
        $failed = false;
        foreach ($plan->recipients as $recipient) {
            foreach ($recipient->channels as $channel) {
                if ($channel->channel === 'database') {
                    continue;
                }
                try {
                    $this->executeChannel($recipient, $channel);
                } catch (\Throwable) {
                    $failed = true;
                }
            }
        }
        foreach ($plan->contexts as $context) {
            try {
                $result = $this->transports->get($context->transport)?->publish($context);
                if ($result === null || ! in_array($result->status, [DeliveryStatus::SENT, DeliveryStatus::DELIVERED], true)) {
                    throw new DeliveryExecutionException;
                }
                $this->logger->info('notification.context_sent', ['notification_id' => $context->payload->id, 'correlation_id' => $context->correlationId, 'transport' => $context->transport]);
            } catch (\Throwable) {
                $failed = true;
                $this->logger->warning('notification.context_failed', ['notification_id' => $context->payload->id, 'correlation_id' => $context->correlationId, 'transport' => $context->transport]);
            }
        }
        $this->logger->info('notification.dispatch_executed', ['notification_id' => $plan->payload->id, 'correlation_id' => $plan->correlationId,
            'recipient_count' => $plan->result()->recipientCount, 'failed' => $failed]);
        if ($failed) {
            throw new DeliveryExecutionException;
        }
    }

    private function executeChannel(DeliveryPlan $plan, ChannelPlan $channel): void
    {
        $ids = [];
        foreach ($channel->destinations ?: [null] as $destination) {
            $id = $this->tracking->create($plan, $channel, $destination);
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        if ($channel->status === ChannelPlanStatus::SKIP) {
            return;
        }
        if (! $channel->queued) {
            $this->runner->run($plan, $channel);

            return;
        }
        try {
            $this->queues->connection($channel->queueConnection)->push(new DeliverNotification($plan, $channel,
                $this->config->get('queue.tries', 3), $this->config->get('queue.backoff', 5)), '', $channel->queue);
            foreach ($ids as $id) {
                // A fast worker may already have advanced the row.
                $this->tracking->transition($id, DeliveryStatus::QUEUED);
            }
        } catch (\Throwable) {
            foreach ($ids as $id) {
                if (in_array($this->tracking->status($id), [DeliveryStatus::PLANNED, DeliveryStatus::QUEUED], true)) {
                    $this->tracking->transition($id, DeliveryStatus::FAILED);
                }
            }
            throw new DeliveryExecutionException;
        }
    }
}
