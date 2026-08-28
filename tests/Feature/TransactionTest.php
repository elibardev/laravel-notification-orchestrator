<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Channels\ChannelDefinition;
use Elibardev\NotificationOrchestrator\Channels\ChannelKind;
use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;
use Elibardev\NotificationOrchestrator\Contracts\DeliveryExecutor;
use Elibardev\NotificationOrchestrator\Contracts\PreferenceRepository;
use Elibardev\NotificationOrchestrator\Contracts\RecipientNormalizer;
use Elibardev\NotificationOrchestrator\Facades\Notify;
use Elibardev\NotificationOrchestrator\Planning\NotificationDispatchPlan;
use Elibardev\NotificationOrchestrator\Preferences\InMemoryPreferenceRepository;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\TestChannel;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\TestNotifiable;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;

class TransactionTest extends TestCase
{
    public function test_execution_failure_after_commit_cannot_rollback_business_data(): void
    {
        config(['notification-orchestrator.features.database' => false, 'notification-orchestrator.features.api' => false,
            'notification-orchestrator.features.queue' => false]);
        app()->instance(DeliveryExecutor::class, new class implements DeliveryExecutor
        {
            public function execute(NotificationDispatchPlan $plan): void
            {
                throw new \RuntimeException('Execution failed after commit.');
            }
        });
        $db = $this->database();
        $db->statement('CREATE TABLE business_effects (id INTEGER PRIMARY KEY)');
        $db->beginTransaction();
        $db->table('business_effects')->insert(['id' => 1]);
        $this->send('x');
        try {
            $db->commit();
            self::fail('Expected an execution failure.');
        } catch (\RuntimeException $error) {
            self::assertSame('Execution failed after commit.', $error->getMessage());
        }
        self::assertSame(0, $db->transactionLevel());
        self::assertSame(1, $db->table('business_effects')->count());
    }

    public function test_other_connections_cannot_release_or_discard_default_connection_work(): void
    {
        Notify::fake();
        config(['database.connections.other' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $db = $this->database();
        $other = app(DatabaseManager::class)->connection('other');
        $db->beginTransaction();
        $other->beginTransaction();
        $this->send('default');
        $other->rollBack();
        Notify::assertNothingSent();
        $db->commit();
        Notify::assertSentTimes('default', 1);
    }

    private function database(): Connection
    {
        return app(DatabaseManager::class)->connection();
    }

    private function send(string $type): void
    {
        Notify::make($type)->title('T')->message('M')->recipients(new TestNotifiable(['id' => 1]))->send();
    }

    public function test_commit_records_frozen_plan_once_without_provider_effects(): void
    {
        app()->singleton(PreferenceRepository::class, InMemoryPreferenceRepository::class);
        $channel = new TestChannel;
        app(ChannelRegistry::class)->register(new ChannelDefinition('test', ChannelKind::OPTIONAL, 'fake', true, true, true, true), $channel, $channel);
        config(['notification-orchestrator.features.test' => true, 'notification-orchestrator.features.preferences' => true, 'queue.default' => 'database']);
        $fake = Notify::fake();
        $db = $this->database();
        $db->beginTransaction();
        $calls = 0;
        $user = new TestNotifiable(['id' => 1]);
        $result = Notify::make('x')->title('T')->message('M')->recipients(function () use (&$calls, $user) {
            $calls++;

            return [$user];
        })->channels(['test'])->send();
        Notify::assertNothingSent();
        self::assertSame(1, $calls);
        self::assertSame(1, $result->plannedQueueJobCount);
        app(PreferenceRepository::class)->set(app(RecipientNormalizer::class)->normalize($user), null, 'test', false);
        $user->setAttribute('id', 2);
        $channel->destinations = ['new-destination'];
        self::assertSame(0, $channel->sent);
        $db->commit();
        Notify::assertSentTimes('x', 1);
        self::assertSame('1', $fake->recorded()[0]->plan->recipients[0]->recipient->id);
        self::assertSame('endpoint-a', array_column($fake->recorded()[0]->plan->recipients[0]->channels, null, 'channel')['test']->destinations[0]->value);
        self::assertSame(1, $calls);
        self::assertSame(1, $channel->resolved);
        self::assertSame(0, $channel->sent);
        self::assertSame($result->notificationId, $fake->recorded()[0]->result->notificationId);
        self::assertSame($result, $fake->recorded()[0]->result);
    }

    public function test_rollback_discards_work_and_preserves_returned_planning_result(): void
    {
        Notify::fake();
        $db = $this->database();
        $db->beginTransaction();
        $result = Notify::make('x')->title('T')->message('M')->recipients(new TestNotifiable(['id' => 1]))->send();
        $db->rollBack();
        Notify::assertNothingSent();
        self::assertSame(1, $result->recipientCount);
    }

    public function test_nested_commit_waits_for_outermost_commit(): void
    {
        Notify::fake();
        $db = $this->database();
        $db->beginTransaction();
        $db->beginTransaction();
        $this->send('inner');
        $db->commit();
        Notify::assertNothingSent();
        $db->commit();
        Notify::assertSentTimes('inner', 1);
    }

    public function test_inner_rollback_discards_its_committed_children_but_not_outer_work(): void
    {
        Notify::fake();
        $db = $this->database();
        $db->beginTransaction();
        $this->send('outer');
        $db->beginTransaction();
        $this->send('inner');
        $db->beginTransaction();
        $this->send('child');
        $db->commit();
        $db->rollBack();
        Notify::assertNothingSent();
        $db->commit();
        Notify::assertSentTimes('outer', 1);
        Notify::assertNotSent('inner');
        Notify::assertNotSent('child');
    }

    public function test_outer_rollback_discards_already_committed_inner_work(): void
    {
        Notify::fake();
        $db = $this->database();
        $db->beginTransaction();
        $db->beginTransaction();
        $this->send('inner');
        $db->commit();
        $db->rollBack();
        Notify::assertNothingSent();
    }

    public function test_sync_and_queue_disabled_modes_still_wait_for_commit(): void
    {
        foreach ([true, false] as $enabled) {
            config(['notification-orchestrator.features.queue' => $enabled, 'queue.default' => 'sync']);
            Notify::fake();
            $db = $this->database();
            $db->beginTransaction();
            $result = Notify::make('x')->title('T')->message('M')->recipients(new TestNotifiable(['id' => 1]))->send();
            self::assertSame(0, $result->plannedQueueJobCount);
            Notify::assertNothingSent();
            $db->commit();
            Notify::assertSent('x');
        }
    }

    public function test_injected_executor_only_runs_after_commit_and_rollback_leaves_no_effects(): void
    {
        config(['notification-orchestrator.features.database' => false, 'notification-orchestrator.features.api' => false, 'notification-orchestrator.features.queue' => false]);
        $executor = new class implements DeliveryExecutor
        {
            /** @var list<NotificationDispatchPlan> */
            public array $plans = [];

            /** @return list<NotificationDispatchPlan> */
            public function recorded(): array
            {
                return $this->plans;
            }

            public function execute(NotificationDispatchPlan $plan): void
            {
                $this->plans[] = $plan;
            }
        };
        app()->instance(DeliveryExecutor::class, $executor);
        $db = $this->database();
        $db->beginTransaction();
        $this->send('rollback');
        self::assertSame([], $executor->plans);
        $db->rollBack();
        self::assertSame([], $executor->plans);
        $db->beginTransaction();
        $this->send('commit');
        self::assertSame([], $executor->plans);
        $db->commit();
        self::assertCount(1, $executor->recorded());
        self::assertNull($executor->recorded()[0]->recipients[0]->storedNotificationId);
        $executor->execute($executor->recorded()[0]);
        self::assertSame($executor->recorded()[0]->payload->id, $executor->recorded()[1]->payload->id);
    }

    public function test_fake_reset_does_not_capture_previously_scheduled_work(): void
    {
        $old = Notify::fake();
        $db = $this->database();
        $db->beginTransaction();
        $this->send('old');
        $new = Notify::fake();
        $db->commit();
        $new->assertNothingSent();
        $old->assertSent('old');
    }
}
