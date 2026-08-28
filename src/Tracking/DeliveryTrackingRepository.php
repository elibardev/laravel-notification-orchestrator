<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tracking;

use Elibardev\NotificationOrchestrator\Channels\ChannelDestination;
use Elibardev\NotificationOrchestrator\Channels\ChannelKind;
use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;
use Elibardev\NotificationOrchestrator\Channels\DeliveryResult;
use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Events\DeliveryStateChanged;
use Elibardev\NotificationOrchestrator\Execution\AfterCommit;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Elibardev\NotificationOrchestrator\Planning\ChannelPlan;
use Elibardev\NotificationOrchestrator\Planning\DeliveryPlan;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;

final class DeliveryTrackingRepository
{
    public function __construct(private Storage $storage, private Configuration $config, private ChannelRegistry $registry, private DeliveryTransitionGuard $guard, private Dispatcher $events, private AfterCommit $afterCommit) {}

    public function id(DeliveryPlan $plan, ChannelPlan $channel, ?ChannelDestination $destination): string
    {
        return hash('sha256', json_encode([$plan->payload->id, $plan->recipient->key(), $channel->channel, $destination?->fingerprint() ?? ''], JSON_THROW_ON_ERROR));
    }

    public function tracks(string $channel): bool
    {
        return $this->config->enabled('delivery_tracking') && ($this->config->get('delivery_tracking.channels', [])[$channel]
            ?? ($this->registry->get($channel)->definition->kind === ChannelKind::OPTIONAL));
    }

    public function create(DeliveryPlan $plan, ChannelPlan $channel, ?ChannelDestination $destination): ?string
    {
        if (! $this->tracks($channel->channel) || ($channel->skipReason !== null && ! $this->config->get('delivery_tracking.record_skipped', true))) {
            return null;
        }
        $id = $this->id($plan, $channel, $destination);
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $status = $channel->skipReason === null ? DeliveryStatus::PLANNED : DeliveryStatus::SKIPPED;
        $inserted = $this->storage->insertIfAbsent('deliveries', [
            'id' => $id, 'notification_id' => $plan->payload->id, 'correlation_id' => $plan->correlationId,
            'notifiable_type' => $plan->recipient->type, 'notifiable_id' => $plan->recipient->id,
            'channel' => $channel->channel, 'driver' => $this->registry->get($channel->channel)->definition->driver,
            'destination_hash' => $destination?->fingerprint() ?? hash('sha256', ''),
            'status' => $status->value, 'skip_reason' => $channel->skipReason?->value,
            'attempts' => 0, 'max_attempts' => $this->config->get('queue.tries', 3),
            'planned_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            'metadata' => json_encode(['stored_notification_id' => $plan->storedNotificationId], JSON_THROW_ON_ERROR),
        ]);
        if ($inserted) {
            $this->emit(new DeliveryStateChanged($id, $plan->payload->id, $plan->correlationId, $channel->channel, $status));
        }

        return $id;
    }

    public function status(string $id): DeliveryStatus
    {
        return DeliveryStatus::from($this->storage->table('deliveries')->where('id', $id)->value('status'));
    }

    public function transition(string $id, DeliveryStatus $to, ?DeliveryResult $result = null): void
    {
        $event = $this->storage->connection()->transaction(function () use ($id, $to, $result) {
            $row = $this->storage->table('deliveries')->where('id', $id)->lockForUpdate()->first() ?? throw new \LogicException('Unknown delivery.');
            $from = DeliveryStatus::from($row->status);
            // Enqueue acknowledgement may arrive after a fast worker advances the row.
            if ($to === DeliveryStatus::QUEUED && in_array($from, [DeliveryStatus::PROCESSING, DeliveryStatus::SENT, DeliveryStatus::DELIVERED], true)) {
                return null;
            }
            $this->guard->assertAllowed($from, $to);
            if ($from === $to && $to !== DeliveryStatus::PROCESSING) {
                return null;
            }
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $values = ['status' => $to->value, 'updated_at' => $now];
            if ($to !== DeliveryStatus::SKIPPED) {
                $values[$to->value.'_at'] = $now;
            }
            if ($to === DeliveryStatus::PROCESSING) {
                $values['attempts'] = $row->attempts + 1;
            }
            if ($to === DeliveryStatus::FAILED) {
                $values['last_error_code'] = 'delivery_failed';
                $values['last_error_message'] = 'Delivery execution failed.';
            }
            if (in_array($to, [DeliveryStatus::SENT, DeliveryStatus::DELIVERED], true)) {
                $values['last_error_code'] = null;
                $values['last_error_message'] = null;
                $values['provider'] = $result?->provider;
                $values['provider_reference'] = $result?->providerReference;
            }
            $this->storage->table('deliveries')->where('id', $id)->update($values);

            return new DeliveryStateChanged($id, $row->notification_id, $row->correlation_id, $row->channel, $to);
        });
        if ($event !== null) {
            $this->emit($event);
        }
    }

    private function emit(DeliveryStateChanged $event): void
    {
        $this->afterCommit->runOn($this->storage->connection(), fn () => $this->events->dispatch($event));
    }
}
