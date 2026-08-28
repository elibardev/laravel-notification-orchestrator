<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Persistence;

use DateTimeInterface;
use Elibardev\NotificationOrchestrator\Contracts\NotificationRepository;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Events\InboxChanged;
use Elibardev\NotificationOrchestrator\Events\StoredNotificationCreated;
use Elibardev\NotificationOrchestrator\Execution\AfterCommit;
use Elibardev\NotificationOrchestrator\Planning\DeliveryPlan;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DatabaseNotificationRepository implements NotificationRepository
{
    public function __construct(private Storage $storage, private RecipientNormalizer $normalizer, private Dispatcher $events, private AfterCommit $afterCommit) {}

    private function owned(object $owner): Builder
    {
        $id = $this->normalizer->normalize($owner);

        return $this->storage->table('notifications')->where('notifiable_type', $id->type)->where('notifiable_id', $id->id);
    }

    private function stored(\stdClass $row): StoredNotification
    {
        return new StoredNotification($row->id, get_object_vars(json_decode($row->data, false, 512, JSON_THROW_ON_ERROR)), $row->read_at === null ? null : Carbon::parse($row->read_at, 'UTC')->format('Y-m-d\TH:i:s.u\Z'), Carbon::parse($row->created_at, 'UTC')->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function store(DeliveryPlan $plan): StoredNotification
    {
        if ($plan->storedNotificationId === null) {
            throw new \LogicException('Persistence requires a planned personal ID.');
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        // Unique planned IDs preserve read state on retries without masking SQL errors.
        $inserted = $this->storage->insertIfAbsent('notifications', [
            'id' => $plan->storedNotificationId, 'type' => $plan->payload->type,
            'notifiable_type' => $plan->recipient->type, 'notifiable_id' => $plan->recipient->id,
            'data' => json_encode((new GenericNotification($plan->payload))->toDatabase(), JSON_THROW_ON_ERROR),
            'read_at' => null, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $stored = $this->findFor($plan->recipient, $plan->storedNotificationId) ?? throw new \LogicException('Stored notification unavailable.');
        if ($stored->payload['id'] !== $plan->payload->id) {
            throw new \LogicException('Conflicting planned notification identity.');
        }
        if ($inserted) {
            $this->afterCommit->runOn($this->storage->connection(), fn () => $this->events->dispatch(new StoredNotificationCreated($plan->recipient, $stored, $plan->correlationId)));
        }

        return $stored;
    }

    public function findFor(object $notifiable, string $storedNotificationId): ?StoredNotification
    {
        $row = $this->owned($notifiable)->where('id', $storedNotificationId)->first();

        return $row === null ? null : $this->stored($row);
    }

    public function unreadCount(object $notifiable): int
    {
        return $this->owned($notifiable)->whereNull('read_at')->count();
    }

    public function paginateFor(object $notifiable, NotificationQuery $query): NotificationPage
    {
        $builder = $this->owned($notifiable);
        if ($query->state === 'read') {
            $builder->whereNotNull('read_at');
        }
        if ($query->state === 'unread') {
            $builder->whereNull('read_at');
        }
        if ($query->type !== null) {
            $builder->where('type', $query->type);
        }
        if ($query->before !== null) {
            [$created, $id] = $query->before;
            $builder->where(fn (Builder $q) => $q->where('created_at', '<', $created)->orWhere(fn (Builder $q) => $q->where('created_at', $created)->where('id', '<', $id)));
        }
        $rows = $builder->orderByDesc('created_at')->orderByDesc('id')->limit($query->limit + 1)->get();
        $more = $rows->count() > $query->limit;
        $rows = $rows->take($query->limit);
        $last = $rows->last();

        return new NotificationPage(array_values($rows->map(fn ($row) => $this->stored($row))->all()),
            $more && $last !== null ? base64_encode(json_encode([$last->created_at, $last->id], JSON_THROW_ON_ERROR)) : null, $this->unreadCount($notifiable));
    }

    public function markRead(object $notifiable, string $storedNotificationId, ?DateTimeInterface $at = null): NotificationStateChange
    {
        return $this->change($notifiable, $storedNotificationId, Carbon::instance($at ?? Carbon::now('UTC'))->utc()->format('Y-m-d H:i:s.u'));
    }

    public function markUnread(object $notifiable, string $storedNotificationId): NotificationStateChange
    {
        return $this->change($notifiable, $storedNotificationId, null);
    }

    private function change(object $owner, string $id, ?string $at): NotificationStateChange
    {
        $result = $this->storage->connection()->transaction(function () use ($owner, $id, $at) {
            $query = $this->owned($owner)->where('id', $id);
            $at === null ? $query->whereNotNull('read_at') : $query->whereNull('read_at');
            $changed = $query->update(['read_at' => $at, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]) > 0;
            $stored = $this->findFor($owner, $id) ?? throw new NotFoundHttpException;

            return new NotificationStateChange($stored, $changed, $this->unreadCount($owner));
        });
        if ($result->changed) {
            $this->emit($at === null ? 'notification.unread' : 'notification.read', $owner, $result->toArray());
        }

        return $result;
    }

    public function markAllRead(object $notifiable, ?DateTimeInterface $at = null): BulkNotificationStateChange
    {
        $date = Carbon::instance($at ?? Carbon::now('UTC'))->utc();
        $result = $this->storage->connection()->transaction(function () use ($notifiable, $date) {
            $changed = $this->owned($notifiable)->whereNull('read_at')->update(['read_at' => $date->format('Y-m-d H:i:s.u'), 'updated_at' => $date->format('Y-m-d H:i:s.u')]);

            return new BulkNotificationStateChange($changed, $this->unreadCount($notifiable), $date->format('Y-m-d\TH:i:s.u\Z'));
        });
        if ($result->changed > 0) {
            $this->emit('notification.read_all', $notifiable, $result->toArray());
        }

        return $result;
    }

    /** @param array<string,mixed> $data */
    private function emit(string $event, object $owner, array $data): void
    {
        $change = new InboxChanged($event, $this->normalizer->normalize($owner), $data);
        $this->afterCommit->runOn($this->storage->connection(), fn () => $this->afterCommit->run(fn () => $this->events->dispatch($change)));
    }
}
