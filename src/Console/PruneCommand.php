<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Console;

use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

final class PruneCommand extends Command
{
    protected $signature = 'notifications:prune {--dry-run} {--only= : notifications, deliveries or devices}';

    protected $description = 'Prune configured retention scopes in chunks; unread inbox is preserved by default.';

    public function handle(Configuration $config, Storage $storage, LoggerInterface $logger): int
    {
        $config->validate();
        $scope = $this->option('only');
        if ($scope !== null && ! in_array($scope, ['notifications', 'deliveries', 'devices'], true)) {
            $this->error('Invalid prune scope.');

            return self::INVALID;
        }
        $dry = (bool) $this->option('dry-run');
        $notifications = 0;
        $deliveries = 0;
        $related = 0;
        $devices = 0;
        $chunk = $config->get('retention.chunk_size', 500);
        if (in_array($scope, [null, 'notifications'], true) && $config->enabled('database') && $config->get('retention.notifications.enabled', false)) {
            $query = $storage->table('notifications')->where('created_at', '<', Carbon::now('UTC')->subDays($config->get('retention.notifications.days'))->format('Y-m-d H:i:s.u'));
            if ($config->get('retention.notifications.only_read', true)) {
                $query->whereNotNull('read_at');
            }
            if ($dry) {
                $notifications = $query->count();
            } else {
                $query->chunkById($chunk, function (Collection $rows) use ($storage, $config, &$notifications, &$related, $query) {
                    foreach ($rows as $row) {
                        $storage->connection()->transaction(function () use ($row, $storage, $config, &$notifications, &$related, $query) {
                            $current = (clone $query)->where('id', $row->id)->lockForUpdate()->first();
                            if ($current === null) {
                                return;
                            }
                            if ($config->enabled('delivery_tracking')) {
                                $payload = json_decode($current->data, true, 512, JSON_THROW_ON_ERROR);
                                $related += $storage->table('deliveries')->where('notification_id', $payload['id'])
                                    ->where('notifiable_type', $current->notifiable_type)->where('notifiable_id', $current->notifiable_id)->delete();
                            }
                            $notifications += $storage->table('notifications')->where('id', $current->id)->delete();
                        });
                    }
                });
            }
        }
        if (in_array($scope, [null, 'deliveries'], true) && $config->enabled('delivery_tracking') && $config->get('delivery_tracking.retention_days') !== null) {
            $query = $storage->table('deliveries')->where('created_at', '<', Carbon::now('UTC')->subDays($config->get('delivery_tracking.retention_days'))->format('Y-m-d H:i:s.u'));
            if ($dry) {
                $deliveries = $query->count();
            } else {
                $query->chunkById($chunk, function (Collection $rows) use ($storage, &$deliveries) {
                    $deliveries += $storage->table('deliveries')->whereIn('id', $rows->pluck('id')->all())->delete();
                });
            }
        }
        if (in_array($scope, [null, 'devices'], true) && $config->enabled('devices') && $config->get('devices.prune_invalidated_after_days') !== null) {
            $query = $storage->table('devices')->where('enabled', false)->whereNotNull('invalidated_at')->where('invalidated_at', '<', Carbon::now('UTC')->subDays($config->get('devices.prune_invalidated_after_days'))->format('Y-m-d H:i:s.u'));
            if ($dry) {
                $devices = $query->count();
            } else {
                $query->chunkById($chunk, function (Collection $rows) use (&$devices, $query) {
                    $devices += (clone $query)->whereIn('id', $rows->pluck('id')->all())->delete();
                });
            }
        }
        $this->line('devices: '.$devices);
        $this->line('notifications: '.$notifications);
        $this->line('deliveries: '.$deliveries);
        $this->line('related deliveries: '.$related);
        if ($dry) {
            $this->info('Dry run: no data changed');
        }
        $logger->info('notification.prune_summary', ['notifications' => $notifications, 'deliveries' => $deliveries, 'devices' => $devices, 'related_deliveries' => $related, 'dry_run' => $dry]);

        return self::SUCCESS;
    }
}
