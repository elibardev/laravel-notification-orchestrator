<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Configuration;

use Elibardev\NotificationOrchestrator\Contracts\ChannelDestinationResolver;
use Elibardev\NotificationOrchestrator\Contracts\PresencePolicy;
use Elibardev\NotificationOrchestrator\Contracts\PushDestinationResolver;
use Elibardev\NotificationOrchestrator\Contracts\RecipientFilter;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Contracts\ReferenceNormalizer;
use Elibardev\NotificationOrchestrator\Exceptions\ConfigurationException;
use Illuminate\Contracts\Config\Repository;

final class Configuration
{
    public function __construct(private Repository $config) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get('notification-orchestrator.'.$key, $default);
    }

    public function enabled(string $name): bool
    {
        return $this->get('features.'.$name, false) === true;
    }

    /** @return list<string> */
    public function errors(): array
    {
        $errors = [];
        $data = $this->config->get('notification-orchestrator');
        if (! is_array($data)) {
            return ['notification-orchestrator must be a map.'];
        }
        foreach (['features', 'database', 'queue', 'broadcast', 'api', 'preferences', 'push', 'delivery_tracking', 'channels', 'recipients'] as $section) {
            if (! is_array($data[$section] ?? null)) {
                $errors[] = $section.' must be a map.';
            }
        }
        foreach ($data as $name => $section) {
            if (is_array($section) && array_key_exists('enabled', $section)) {
                $errors[] = 'Use features.'.$name.' instead of '.$name.'.enabled.';
            }
        }
        foreach ((array) ($data['features'] ?? []) as $name => $value) {
            if (! is_string($name) || ! preg_match('/^[a-z][a-z0-9._-]*$/D', $name) || ! is_bool($value)) {
                $errors[] = 'Feature switches require registered names and boolean values.';
            }
        }
        foreach (['database.connection', 'queue.connection', 'broadcast.connection', 'broadcast.queue'] as $key) {
            $value = $this->get($key);
            if ($value !== null && (! is_string($value) || trim($value) === '')) {
                $errors[] = $key.' must be null or a non-empty string.';
            }
        }
        $prefix = $this->get('database.table_prefix');
        if (! is_string($prefix) || ! preg_match('/^[a-zA-Z0-9_]*$/D', $prefix)) {
            $errors[] = 'database.table_prefix must be a portable identifier prefix.';
        }
        $tables = $this->get('database.tables');
        if (! is_array($tables)) {
            $errors[] = 'database.tables must be a map.';
        } else {
            foreach ($tables as $name => $table) {
                if (! in_array($name, ['notifications', 'preferences', 'devices', 'deliveries'], true)
                    || ($table !== null && (! is_string($table) || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $table)))) {
                    $errors[] = 'database.tables contains an invalid logical table or identifier.';
                }
            }
        }
        foreach (['channels.defaults', 'api.middleware', 'recipients.filters'] as $key) {
            if (! $this->stringList($this->get($key))) {
                $errors[] = $key.' must be a list of strings.';
            }
        }
        foreach (['channels.types', 'preferences.defaults', 'preferences.types'] as $key) {
            if (! is_array($this->get($key))) {
                $errors[] = $key.' must be a map.';
            }
        }
        foreach ((array) $this->get('channels.types', []) as $list) {
            if (! $this->stringList($list)) {
                $errors[] = 'channels.types values must be channel lists.';
            }
        }
        foreach (['preferences.defaults', 'preferences.types'] as $key) {
            foreach ((array) $this->get($key, []) as $value) {
                $values = $key === 'preferences.types' ? $value : [$value];
                if (! is_array($values)) {
                    $errors[] = $key.' has an invalid map.';

                    continue;
                }
                foreach ($values as $enabled) {
                    if ($enabled !== null && ! is_bool($enabled)) {
                        $errors[] = $key.' accepts only boolean or null preferences.';
                    }
                }
            }
        }
        if (! is_bool($this->get('preferences.default'))) {
            $errors[] = 'preferences.default must be boolean.';
        }
        if ($this->enabled('presence') && (! is_string($this->get('presence.policy')) || ! is_a($this->get('presence.policy'), PresencePolicy::class, true))) {
            $errors[] = 'Enabled presence requires a PresencePolicy class.';
        }
        if ($this->enabled('push') && (! is_string($this->get('push.destination_resolver')) || ! is_a($this->get('push.destination_resolver'), PushDestinationResolver::class, true))) {
            $errors[] = 'push.destination_resolver must implement PushDestinationResolver.';
        }
        if ($this->enabled('devices') && $this->get('devices.prune_invalidated_after_days') !== null && (! is_int($this->get('devices.prune_invalidated_after_days')) || $this->get('devices.prune_invalidated_after_days') < 1)) {
            $errors[] = 'Device pruning requires positive days or null.';
        }
        if ($this->enabled('mqtt') && (! in_array($this->get('mqtt.qos'), [0, 1], true) || ! is_bool($this->get('mqtt.retain')))) {
            $errors[] = 'MQTT requires QoS 0/1 and boolean retain.';
        }
        foreach (['recipients.normalizer' => RecipientNormalizer::class,
            'references.normalizer' => ReferenceNormalizer::class] as $key => $contract) {
            if (! is_string($this->get($key)) || ! is_a($this->get($key), $contract, true)) {
                $errors[] = $key.' must name a class implementing its normalization contract.';
            }
        }
        foreach ((array) $this->get('recipients.filters', []) as $filter) {
            if (! is_string($filter) || ! is_a($filter, RecipientFilter::class, true)) {
                $errors[] = 'recipients.filters must implement RecipientFilter.';
            }
        }
        if (! is_array($this->get('channels.destinations'))) {
            $errors[] = 'channels.destinations must be a map.';
        }
        foreach ((array) $this->get('channels.destinations', []) as $name => $resolver) {
            if ($this->enabled((string) $name) && (! is_string($resolver) || ! is_a($resolver, ChannelDestinationResolver::class, true))) {
                $errors[] = 'Enabled channel destinations require ChannelDestinationResolver classes.';
            }
        }
        if ($this->enabled('queue') && (! is_string($this->get('queue.queue')) || trim($this->get('queue.queue')) === '')) {
            $errors[] = 'queue.queue is required.';
        }
        if ($this->enabled('delivery_tracking') && $this->get('delivery_tracking.retention_days') !== null && (! is_int($this->get('delivery_tracking.retention_days')) || $this->get('delivery_tracking.retention_days') < 1)) {
            $errors[] = 'delivery_tracking.retention_days must be positive.';
        }
        foreach (['queue.tries', 'retention.chunk_size'] as $key) {
            if (! is_int($this->get($key)) || $this->get($key) < 1) {
                $errors[] = $key.' must be a positive integer.';
            }
        }
        if (! is_int($this->get('queue.backoff')) || $this->get('queue.backoff') < 0) {
            $errors[] = 'queue.backoff must be non-negative.';
        }
        foreach (['retention.notifications.enabled', 'retention.notifications.only_read', 'blade.styles', 'delivery_tracking.record_skipped'] as $key) {
            if (! is_bool($this->get($key))) {
                $errors[] = $key.' must be boolean.';
            }
        }
        if ($this->get('retention.notifications.enabled') && (! is_int($this->get('retention.notifications.days')) || $this->get('retention.notifications.days') < 1)) {
            $errors[] = 'Enabled inbox retention requires positive days.';
        }
        if (! is_array($this->get('delivery_tracking.channels'))) {
            $errors[] = 'delivery_tracking.channels must be a map.';
        }
        foreach ((array) $this->get('delivery_tracking.channels', []) as $value) {
            if (! is_bool($value)) {
                $errors[] = 'Tracking channel selection must be boolean.';
            }
        }
        if ($this->enabled('api') || $this->enabled('devices')) {
            foreach (['api.prefix', 'api.name_prefix'] as $key) {
                if (! is_string($this->get($key)) || trim($this->get($key)) === '') {
                    $errors[] = $key.' must be a non-empty string.';
                }
            }
        }

        return array_values(array_unique($errors));
    }

    public function validate(): void
    {
        if ($errors = $this->errors()) {
            throw new ConfigurationException(implode(' ', $errors));
        }
    }

    private function stringList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && count(array_filter($value, fn ($item) => is_string($item) && trim($item) !== '')) === count($value);
    }
}
