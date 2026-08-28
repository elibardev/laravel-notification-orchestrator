<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Execution;

use Elibardev\NotificationOrchestrator\Channels\ChannelDelivery;
use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;
use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;
use Elibardev\NotificationOrchestrator\Exceptions\DeliveryExecutionException;
use Elibardev\NotificationOrchestrator\Planning\ChannelPlan;
use Elibardev\NotificationOrchestrator\Planning\DeliveryPlan;
use Elibardev\NotificationOrchestrator\Tracking\DeliveryTrackingRepository;
use Psr\Log\LoggerInterface;

final class ChannelRunner
{
    public function __construct(private ChannelRegistry $registry, private DeliveryTrackingRepository $tracking, private LoggerInterface $logger) {}

    public function run(DeliveryPlan $plan, ChannelPlan $channel): void
    {
        $failed = false;
        foreach ($channel->destinations ?: [null] as $destination) {
            $id = $this->tracking->create($plan, $channel, $destination);
            if ($id !== null && in_array($this->tracking->status($id), [DeliveryStatus::SENT, DeliveryStatus::DELIVERED], true)) {
                continue;
            }
            try {
                if ($id !== null) {
                    $this->tracking->transition($id, DeliveryStatus::PROCESSING);
                }
                $single = new ChannelPlan($channel->channel, $channel->status, $destination === null ? [] : [$destination], null, false);
                $result = $this->registry->get($channel->channel)->channel()?->send(new ChannelDelivery($plan, $single));
                if ($result === null || $result->channel !== $channel->channel || ! in_array($result->status, [DeliveryStatus::SENT, DeliveryStatus::DELIVERED], true)) {
                    throw new DeliveryExecutionException;
                }
                if ($id !== null) {
                    $this->tracking->transition($id, DeliveryStatus::SENT, $result);
                    if ($result->status === DeliveryStatus::DELIVERED) {
                        $this->tracking->transition($id, DeliveryStatus::DELIVERED, $result);
                    }
                }
            } catch (\Throwable) {
                $failed = true;
                if ($id !== null) {
                    $this->tracking->transition($id, DeliveryStatus::FAILED);
                }
                $this->logger->warning('notification.delivery_failed', ['notification_id' => $plan->payload->id, 'correlation_id' => $plan->correlationId, 'delivery_id' => $id, 'channel' => $channel->channel]);
            }
        }
        if ($failed) {
            throw new DeliveryExecutionException;
        }
    }
}
