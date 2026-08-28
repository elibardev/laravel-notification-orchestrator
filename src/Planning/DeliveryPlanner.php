<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Planning;

use Elibardev\NotificationOrchestrator\Channels\ChannelKind;
use Elibardev\NotificationOrchestrator\Channels\ChannelPlanStatus;
use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;
use Elibardev\NotificationOrchestrator\Channels\SkipReason;
use Elibardev\NotificationOrchestrator\Configuration\CapabilityRegistry;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Context\ContextDeliveryPlan;
use Elibardev\NotificationOrchestrator\Context\ContextTarget;
use Elibardev\NotificationOrchestrator\Context\ContextTransportRegistry;
use Elibardev\NotificationOrchestrator\Contracts\IdGenerator;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelNotFoundException;
use Elibardev\NotificationOrchestrator\Exceptions\ConfigurationException;
use Elibardev\NotificationOrchestrator\Exceptions\MissingRecipientsException;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\NotificationPayload;
use Elibardev\NotificationOrchestrator\Preferences\PreferenceResolver;
use Elibardev\NotificationOrchestrator\Presence\PresenceEvaluator;
use Elibardev\NotificationOrchestrator\Recipients\RecipientPipeline;
use Illuminate\Contracts\Config\Repository;

final class DeliveryPlanner
{
    public function __construct(private Configuration $config, private CapabilityRegistry $capabilities, private ChannelRegistry $registry,
        private ContextTransportRegistry $transports, private RecipientPipeline $recipients, private RecipientNormalizer $normalizer,
        private PreferenceResolver $preferences, private IdGenerator $ids, private Repository $applicationConfig, private PresenceEvaluator $presence) {}

    /** @param list<mixed> $sources
     * @param  list<mixed>  $exclusions
     * @param  list<string>|null  $requested
     * @param  list<ContextTarget>  $targets
     */
    public function plan(NotificationContext $context, array $sources, array $exclusions = [], ?array $requested = null,
        array $targets = [], bool $planningOnly = false): NotificationDispatchPlan
    {
        if ($sources === []) {
            throw new MissingRecipientsException('At least one recipient source is required.');
        }
        $this->config->validate();
        $this->capabilities->validate();
        $this->registry->validateEnabled($planningOnly);
        $this->transports->validateEnabled($planningOnly);
        $requested ??= $this->config->get('channels.types', [])[$context->type] ?? $this->config->get('channels.defaults', []);
        foreach ($requested as $name) {
            if ($this->registry->get($name)->definition->kind === ChannelKind::STRUCTURAL) {
                throw new ConfigurationException('channels() accepts optional channels only.');
            }
        }
        $connection = $this->config->get('queue.connection') ?? $this->applicationConfig->get('queue.default');
        $driver = is_string($connection) ? $this->applicationConfig->get('queue.connections.'.$connection.'.driver') : null;
        if ($this->config->enabled('queue') && ! is_string($driver)) {
            throw new ConfigurationException('Configured queue connection is unavailable.');
        }
        $asynchronous = $this->config->enabled('queue') && ! in_array($driver, ['sync', 'null'], true);
        $payload = new NotificationPayload($this->ids->generate(), $context);
        $correlation = $this->ids->generate();
        $plans = [];
        foreach ($this->recipients->resolve($sources, $exclusions, $context) as $recipient) {
            $identity = $this->normalizer->normalize($recipient);
            $channels = [];
            foreach ($this->registry->all() as $registered) {
                $definition = $registered->definition;
                $name = $definition->name;
                $structural = $definition->kind === ChannelKind::STRUCTURAL;
                if ($structural && ! $this->config->enabled($name)) {
                    continue;
                }
                $reason = null;
                $destinations = [];
                if (! $this->config->enabled($name)) {
                    $reason = SkipReason::DISABLED;
                } elseif (! $structural && ! in_array($name, $requested, true)) {
                    $reason = SkipReason::NOT_REQUESTED;
                } elseif ($definition->preferenceAware && ! $this->preferences->enabled($identity, $context->type, $name)) {
                    $reason = SkipReason::USER_PREFERENCE;
                } elseif (! $structural && $this->presence->suppress($identity, $context, $name)) {
                    $reason = SkipReason::PRESENCE;
                } elseif ($definition->requiresDestination) {
                    foreach ($registered->resolver()?->resolve($recipient, $context) ?? [] as $destination) {
                        $destinations[$destination->fingerprint()] = $destination;
                    }
                    if ($destinations === []) {
                        if ($structural) {
                            throw new ChannelConfigurationException('Structural channel '.$name.' requires a destination resolver.');
                        }
                        $reason = SkipReason::NO_DESTINATION;
                    }
                }
                $queued = $reason === null && $definition->queueable && $asynchronous;
                $channels[] = new ChannelPlan($name, $reason === null ? ChannelPlanStatus::DELIVER : ChannelPlanStatus::SKIP,
                    array_values($destinations), $reason, $queued, $queued ? $connection : null,
                    $queued ? ($this->config->get($name.'.queue') ?? $this->config->get('queue.queue')) : null);
            }
            $plans[] = new DeliveryPlan($identity, $payload, $this->config->enabled('database') ? $this->ids->generate() : null, $channels, $correlation);
        }
        $contexts = [];
        foreach ($targets as $target) {
            if (! $this->transports->has($target->transport)) {
                throw new ChannelNotFoundException('Unknown requested context transport.');
            }
            if (! $this->config->enabled($target->transport)) {
                throw new ConfigurationException('Requested context transport is disabled.');
            }
            if (! $planningOnly && $this->transports->get($target->transport) === null) {
                throw new ChannelConfigurationException('Requested context transport is not implemented.');
            }
            $contexts[] = new ContextDeliveryPlan($target->transport, $target->destination, $payload, $target->options, $correlation);
        }

        return new NotificationDispatchPlan($context, $payload, $correlation, $plans, $contexts);
    }
}
