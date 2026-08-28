<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Console;

use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;
use Elibardev\NotificationOrchestrator\Channels\HealthStatus;
use Elibardev\NotificationOrchestrator\Configuration\CapabilityRegistry;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Configuration\RuntimeHealth;
use Elibardev\NotificationOrchestrator\Context\ContextTransportRegistry;
use Illuminate\Console\Command;

final class StatusCommand extends Command
{
    protected $signature = 'notifications:status';

    protected $description = 'Diagnose orchestrator configuration and implemented capabilities without exposing secrets.';

    public function handle(Configuration $config, CapabilityRegistry $capabilities, ChannelRegistry $channels, ContextTransportRegistry $transports, RuntimeHealth $health): int
    {
        $this->line('Elibardev Notification Orchestrator — development (target 0.1.0)');
        $this->line('Laravel '.$this->laravel->version().' / PHP '.PHP_VERSION);
        $this->line('Payload schema 1.0; Phases 1–3 implemented. Configuration checks do not probe external providers.');
        $errors = array_merge($config->errors(), $capabilities->errors(), $health->errors());
        foreach ($errors as $error) {
            $this->error($error);
        }
        $invalid = $errors !== [];
        $degraded = false;
        foreach ($capabilities->all() as $name => $capability) {
            if (! $config->enabled($name)) {
                $this->line($name.': disabled');

                continue;
            }
            if (! $capability['implemented']) {
                $this->line($name.': INVALID — delivery/module not implemented');
                $invalid = true;
            }
        }
        foreach ($channels->enabled() as $channel) {
            if (! $channel->implemented()) {
                continue;
            }
            try {
                $implementation = $channel->channel();
                if ($implementation?->name() !== $channel->definition->name) {
                    throw new \LogicException;
                }
                $implementation->validateConfiguration();
            } catch (\Throwable) {
                $invalid = true;
                $this->line($channel->definition->name.': INVALID — configuration rejected');

                continue;
            }
            try {
                $state = $channel->definition->healthCheckable ? $implementation->health()->status : HealthStatus::HEALTHY;
            } catch (\Throwable) {
                $state = HealthStatus::DEGRADED;
            }
            $invalid = $invalid || $state === HealthStatus::INVALID;
            $degraded = $degraded || $state === HealthStatus::DEGRADED;
            $this->line($channel->definition->name.': '.$state->value);
        }
        foreach ($transports->names() as $name) {
            if (! $config->enabled($name)) {
                continue;
            }
            try {
                $transport = $transports->get($name);
                if ($transport === null) {
                    $this->line('context '.$name.': INVALID — implementation unavailable');
                    $invalid = true;

                    continue;
                }
                if ($transport->name() !== $name) {
                    throw new \LogicException;
                }
                $transport->validateConfiguration();
            } catch (\Throwable) {
                $invalid = true;
                $this->line('context '.$name.': INVALID');

                continue;
            }
            try {
                $state = $transport->health()->status;
            } catch (\Throwable) {
                $state = HealthStatus::DEGRADED;
            }
            $invalid = $invalid || $state === HealthStatus::INVALID;
            $degraded = $degraded || $state === HealthStatus::DEGRADED;
            $this->line('context '.$name.': '.$state->value);
        }
        $this->line('Inbox retention: '.($config->get('retention.notifications.enabled') ? 'configured' : 'disabled'));
        $this->line('Delivery retention: '.($config->enabled('delivery_tracking') && $config->get('delivery_tracking.retention_days') !== null ? $config->get('delivery_tracking.retention_days').' days' : 'disabled'));
        $this->line('Invalidated device retention: '.($config->enabled('devices') && $config->get('devices.prune_invalidated_after_days') !== null ? $config->get('devices.prune_invalidated_after_days').' days' : 'disabled'));
        $this->line('Overall: '.($invalid ? 'INVALID' : ($degraded ? 'DEGRADED' : 'HEALTHY')));

        return $invalid ? 1 : ($degraded ? 2 : 0);
    }
}
