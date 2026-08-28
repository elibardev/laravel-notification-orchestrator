<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Channels\ChannelDefinition;
use Elibardev\NotificationOrchestrator\Channels\ChannelKind;
use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;
use Elibardev\NotificationOrchestrator\Channels\SkipReason;
use Elibardev\NotificationOrchestrator\Context\ContextTarget;
use Elibardev\NotificationOrchestrator\Context\ContextTransportRegistry;
use Elibardev\NotificationOrchestrator\Contracts\PreferenceRepository;
use Elibardev\NotificationOrchestrator\Contracts\RecipientFilter;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Contracts\RecipientResolver;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelAlreadyRegisteredException;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelNotFoundException;
use Elibardev\NotificationOrchestrator\Exceptions\ConfigurationException;
use Elibardev\NotificationOrchestrator\Exceptions\InvalidRecipientException;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\Planning\DeliveryPlanner;
use Elibardev\NotificationOrchestrator\Preferences\InMemoryPreferenceRepository;
use Elibardev\NotificationOrchestrator\Push\PushDriverRegistry;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\ExternalPushResolver;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\FakePushDriver;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\TestChannel;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\TestNotifiable;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\TestTransport;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\UnusableChannel;
use Elibardev\NotificationOrchestrator\Tests\TestCase;

class PlanningTest extends TestCase
{
    public function test_unknown_context_transport_throws_instead_of_being_ignored(): void
    {
        $this->expectException(ChannelNotFoundException::class);
        app(DeliveryPlanner::class)->plan(new NotificationContext('x', 'T', 'M'), [[]],
            targets: [ContextTarget::custom('unknown', 'record.1')], planningOnly: true);
    }

    public function test_context_transport_registration_cannot_silently_replace_a_builtin(): void
    {
        $this->expectException(ChannelAlreadyRegisteredException::class);
        app(ContextTransportRegistry::class)->register('mqtt');
    }

    public function test_disabled_context_transport_is_an_explicit_configuration_error(): void
    {
        $this->expectException(ConfigurationException::class);
        app(DeliveryPlanner::class)->plan(new NotificationContext('x', 'T', 'M'), [[]],
            targets: [ContextTarget::mqtt('records/1')], planningOnly: true);
    }

    public function test_disabled_provider_is_not_instantiated_or_validated(): void
    {
        app(ChannelRegistry::class)->register(new ChannelDefinition('disabled', ChannelKind::OPTIONAL, 'test', true, true, true, true),
            UnusableChannel::class);
        config(['notification-orchestrator.features.disabled' => false]);
        $plan = app(DeliveryPlanner::class)->plan(new NotificationContext('x', 'T', 'M'), [[]], planningOnly: true);
        self::assertSame(0, $plan->result()->recipientCount);
    }

    public function test_builtin_multi_destination_counts_are_per_recipient_channel_not_device(): void
    {
        app(PushDriverRegistry::class)->register('fake', new FakePushDriver);
        config(['notification-orchestrator.push.default_driver' => 'fake', 'notification-orchestrator.push.destination_resolver' => ExternalPushResolver::class]);
        config(['queue.default' => 'database', 'notification-orchestrator.features.mail' => true, 'notification-orchestrator.features.push' => true,
            'notification-orchestrator.channels.destinations.mail' => TestChannel::class,
            'notification-orchestrator.channels.destinations.push' => TestChannel::class]);
        $plan = app(DeliveryPlanner::class)->plan(new NotificationContext('x', 'T', 'M'),
            [[new TestNotifiable(['id' => 1]), new TestNotifiable(['id' => 2])]], requested: ['mail', 'push'], planningOnly: true);
        self::assertSame(4, $plan->result()->plannedQueueJobCount);
        self::assertCount(2, array_column($plan->recipients[0]->channels, null, 'channel')['push']->destinations);
    }

    public function test_event_defaults_preferences_and_explicit_empty_channel_list(): void
    {
        $this->channel();
        config(['notification-orchestrator.channels.defaults' => ['test'],
            'notification-orchestrator.channels.types' => ['record.created' => []]]);
        $planner = app(DeliveryPlanner::class);
        $user = new TestNotifiable(['id' => 1]);
        $perType = $planner->plan(new NotificationContext('record.created', 'T', 'M'), [$user], planningOnly: true);
        self::assertSame(SkipReason::NOT_REQUESTED, array_column($perType->recipients[0]->channels, null, 'channel')['test']->skipReason);
        $global = $planner->plan(new NotificationContext('other', 'T', 'M'), [$user], planningOnly: true);
        self::assertNull(array_column($global->recipients[0]->channels, null, 'channel')['test']->skipReason);
        $empty = $planner->plan(new NotificationContext('other', 'T', 'M'), [$user], requested: [], planningOnly: true);
        self::assertSame(SkipReason::NOT_REQUESTED, array_column($empty->recipients[0]->channels, null, 'channel')['test']->skipReason);
    }

    public function test_structural_preferences_are_rejected(): void
    {
        $identity = app(RecipientNormalizer::class)->normalize(new TestNotifiable(['id' => 1]));
        $this->expectException(ConfigurationException::class);
        app(PreferenceRepository::class)->set($identity, null, 'database', false);
    }

    public function test_resolver_classes_instances_closures_and_filters_compose(): void
    {
        $resolver = new class implements RecipientResolver
        {
            public function resolve(NotificationContext $context): iterable
            {
                return [new TestNotifiable(['id' => 1]), new TestNotifiable(['id' => 2])];
            }
        };
        $filter = new class implements RecipientFilter
        {
            public function filter(iterable $recipients, NotificationContext $context): iterable
            {
                foreach ($recipients as $user) {
                    if ($user instanceof TestNotifiable && (string) $user->getKey() !== '2') {
                        yield $user;
                    }
                }
            }
        };
        app()->instance($resolver::class, $resolver);
        app()->instance($filter::class, $filter);
        config(['notification-orchestrator.recipients.filters' => [$filter::class]]);
        $plan = app(DeliveryPlanner::class)->plan(new NotificationContext('x', 'T', 'M'),
            [$resolver::class, $resolver, fn () => collect([new TestNotifiable(['id' => 3])])],
            [fn () => [new TestNotifiable(['id' => 3])]], planningOnly: true);
        self::assertSame(1, $plan->result()->recipientCount);
        self::assertSame('1', $plan->recipients[0]->recipient->id);
    }

    public function test_filters_cannot_reintroduce_excluded_or_unrelated_recipients(): void
    {
        $filter = new class implements RecipientFilter
        {
            public function filter(iterable $recipients, NotificationContext $context): iterable
            {
                return [new TestNotifiable(['id' => 2])];
            }
        };
        app()->instance($filter::class, $filter);
        config(['notification-orchestrator.recipients.filters' => [$filter::class]]);
        $this->expectException(InvalidRecipientException::class);
        app(DeliveryPlanner::class)->plan(new NotificationContext('x', 'T', 'M'), [new TestNotifiable(['id' => 1])], planningOnly: true);
    }

    private function channel(string $name = 'test'): TestChannel
    {
        $channel = new TestChannel($name);
        app(ChannelRegistry::class)->register(new ChannelDefinition($name, ChannelKind::OPTIONAL, 'test', true, true, true, true), $channel, $channel);
        config(['notification-orchestrator.features.'.$name => true]);

        return $channel;
    }

    public function test_identity_exclusions_and_counts_are_planned_without_delivery(): void
    {
        $channel = $this->channel();
        config(['queue.default' => 'database', 'notification-orchestrator.features.custom' => true]);
        $transport = new TestTransport;
        app(ContextTransportRegistry::class)->register('custom', $transport);
        $a = new TestNotifiable(['id' => 1]);
        $b = new TestNotifiable(['id' => 2]);
        $context = new NotificationContext('record.created', 'Title', 'Message');
        $plan = app(DeliveryPlanner::class)->plan($context, [$a, [$b, $a]], requested: ['test'],
            targets: [ContextTarget::custom('custom', 'records/1')], planningOnly: true);
        self::assertSame(2, $plan->result()->recipientCount);
        self::assertSame(2, $plan->result()->plannedQueueJobCount);
        self::assertSame(1, $plan->result()->contextDeliveryCount);
        self::assertNotSame($plan->recipients[0]->storedNotificationId, $plan->recipients[1]->storedNotificationId);
        self::assertSame($plan->payload->id, $plan->recipients[1]->payload->id);
        self::assertSame(2, $channel->resolved);
        self::assertSame(0, $channel->sent);
        self::assertSame(0, $transport->published);
        $a->setAttribute('id', 90);
        $channel->destinations = [];
        self::assertSame('1', $plan->recipients[0]->recipient->id);
        self::assertCount(2, array_column($plan->recipients[0]->channels, null, 'channel')['test']->destinations);
        $excluded = app(DeliveryPlanner::class)->plan($context, [[$a, $b]], [$b], [], planningOnly: true);
        self::assertSame(1, $excluded->result()->recipientCount);
    }

    public function test_preference_inheritance_and_all_skip_reasons(): void
    {
        app()->singleton(PreferenceRepository::class, InMemoryPreferenceRepository::class);
        $channel = $this->channel();
        $unused = $this->channel('unused');
        $missing = $this->channel('missing');
        $missing->destinations = [];
        config(['notification-orchestrator.features.preferences' => true, 'notification-orchestrator.preferences.defaults.test' => false,
            'notification-orchestrator.preferences.types' => ['record.created' => ['test' => true]]]);
        $user = new TestNotifiable(['id' => 1]);
        $identity = app(RecipientNormalizer::class)->normalize($user);
        $repository = app(PreferenceRepository::class);
        $repository->set($identity, null, 'test', false);
        $repository->set($identity, 'record.created', 'test', true);
        $context = new NotificationContext('record.created', 'T', 'M');
        $planner = app(DeliveryPlanner::class);
        $make = fn () => $planner->plan($context, [$user], requested: ['test', 'missing'], planningOnly: true);
        $plan = $make();
        $byName = [];
        foreach ($plan->recipients[0]->channels as $entry) {
            $byName[$entry->channel] = $entry;
        }
        self::assertNull($byName['test']->skipReason);
        self::assertSame(SkipReason::DISABLED, $byName['push']->skipReason);
        self::assertSame(SkipReason::NOT_REQUESTED, $byName['unused']->skipReason);
        self::assertSame(SkipReason::NO_DESTINATION, $byName['missing']->skipReason);
        $repository->delete($identity, 'record.created', 'test');
        self::assertSame(SkipReason::USER_PREFERENCE, array_column($make()->recipients[0]->channels, null, 'channel')['test']->skipReason);
        $repository->delete($identity, null, 'test');
        self::assertNull(array_column($make()->recipients[0]->channels, null, 'channel')['test']->skipReason);
        self::assertSame(0, $unused->resolved);
    }

    public function test_duplicate_channels_never_replace_existing_definitions(): void
    {
        $this->channel();
        $this->expectException(ChannelAlreadyRegisteredException::class);
        $this->channel();
    }

    public function test_explicit_unknown_channel_fails_even_with_no_resolved_recipients(): void
    {
        $this->expectException(ChannelNotFoundException::class);
        app(DeliveryPlanner::class)->plan(new NotificationContext('x', 'T', 'M'), [[]], requested: ['unknown'], planningOnly: true);
    }

    public function test_enabled_invalid_channel_fails_before_destination_resolution(): void
    {
        $channel = $this->channel();
        $channel->valid = false;
        $this->expectException(ChannelConfigurationException::class);
        app(DeliveryPlanner::class)->plan(new NotificationContext('x', 'T', 'M'), [new TestNotifiable(['id' => 1])], requested: [], planningOnly: true);
    }
}
