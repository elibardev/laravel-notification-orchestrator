<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Channels\ChannelDefinition;
use Elibardev\NotificationOrchestrator\Channels\ChannelKind;
use Elibardev\NotificationOrchestrator\Channels\ChannelRegistry;
use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;
use Elibardev\NotificationOrchestrator\Exceptions\DeliveryExecutionException;
use Elibardev\NotificationOrchestrator\Facades\Notify;
use Elibardev\NotificationOrchestrator\Persistence\PackageSchema;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\DeliveryChannel;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Elibardev\NotificationOrchestrator\Tracking\DeliveryTransitionGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Psr\Log\LoggerInterface;

class ExecutionTest extends TestCase
{
    public function test_failure_isolation_and_safe_logging_across_recipients(): void
    {
        $channel = $this->setupDelivery();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning')->with('notification.delivery_failed', self::callback(function (array $context): bool {
            $json = json_encode($context, JSON_THROW_ON_ERROR);

            return isset($context['notification_id'],$context['correlation_id']) && ! str_contains($json, 'secret') && ! str_contains($json, 'SECRET');
        }));
        app()->instance(LoggerInterface::class, $logger);
        try {
            Notify::make('x')->title('T')->message('M')->recipients([new RecipientIdentity('account', '1'), new RecipientIdentity('account', '2')])->channels(['test'])->send();
            self::fail('Expected delivery failure.');
        } catch (DeliveryExecutionException $error) {
            self::assertNull($error->getPrevious());
            self::assertStringNotContainsString('SECRET', $error->getMessage());
        }
        self::assertCount(4, $channel->calls);
        self::assertSame(2, app(Storage::class)->table('notifications')->count());
        self::assertSame(2, app(Storage::class)->table('deliveries')->where('status', 'sent')->count());
    }

    private function setupDelivery(bool $inbox = true): DeliveryChannel
    {
        config(['notification-orchestrator.features.database' => $inbox, 'notification-orchestrator.features.api' => $inbox,
            'notification-orchestrator.features.delivery_tracking' => true, 'notification-orchestrator.features.test' => true,
            'notification-orchestrator.queue.backoff' => 0]);
        if ($inbox) {
            app(PackageSchema::class)->create('notifications');
        }
        app(PackageSchema::class)->create('deliveries');
        $channel = new DeliveryChannel;
        app(ChannelRegistry::class)->register(new ChannelDefinition('test', ChannelKind::OPTIONAL, 'fake', true, true, true, true), $channel, $channel);

        return $channel;
    }

    private function queue(): void
    {
        config(['queue.default' => 'database']);
        app(Storage::class)->connection()->getSchemaBuilder()->create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    private function work(): void
    {
        $worker = app('queue.worker');
        self::assertInstanceOf(Worker::class, $worker);
        $worker->runNextJob('database', 'notifications', new WorkerOptions(sleep: 0, maxTries: 3));
    }

    public function test_real_database_worker_retries_same_rows_and_skips_successful_destinations(): void
    {
        $channel = $this->setupDelivery();
        $this->queue();
        $result = Notify::make('x')->title('T')->message('M')->recipients(new RecipientIdentity('account', '1'))->channels(['test'])->send();
        self::assertSame(1, $result->plannedQueueJobCount);
        self::assertSame(1, app(Storage::class)->connection()->table('jobs')->count());
        self::assertSame(1, app(Storage::class)->table('notifications')->count());
        self::assertSame(['queued', 'queued'], app(Storage::class)->table('deliveries')->where('channel', 'test')->orderBy('destination_hash')->pluck('status')->all());
        $ids = app(Storage::class)->table('deliveries')->orderBy('id')->pluck('id')->all();
        $this->work();
        self::assertSame(['safe', 'secret-token'], $channel->calls);
        self::assertSame(1, app(Storage::class)->connection()->table('jobs')->count());
        self::assertSame(1, app(Storage::class)->table('deliveries')->where('status', 'failed')->count());
        $channel->fail = false;
        $this->work();
        self::assertSame(['safe', 'secret-token', 'secret-token'], $channel->calls);
        self::assertSame(0, app(Storage::class)->connection()->table('jobs')->count());
        self::assertSame($ids, app(Storage::class)->table('deliveries')->orderBy('id')->pluck('id')->all());
        self::assertSame(2, app(Storage::class)->table('deliveries')->where('status', 'sent')->count());
        self::assertSame(2, app(Storage::class)->table('deliveries')->max('attempts'));
        self::assertStringNotContainsString('secret-token', json_encode(app(Storage::class)->table('deliveries')->get(), JSON_THROW_ON_ERROR));
        self::assertSame(0, app(Storage::class)->table('deliveries')->whereNotNull('last_error_code')->count());
    }

    public function test_commit_and_rollback_gate_storage_tracking_and_jobs(): void
    {
        $channel = $this->setupDelivery();
        $channel->fail = false;
        $this->queue();
        $db = app(Storage::class)->connection();
        $db->beginTransaction();
        Notify::make('x')->title('T')->message('M')->recipients(new RecipientIdentity('account', '1'))->channels(['test'])->send();
        self::assertSame(0, app(Storage::class)->table('notifications')->count());
        self::assertSame(0, app(Storage::class)->table('deliveries')->count());
        self::assertSame(0, $db->table('jobs')->count());
        $db->rollBack();
        self::assertSame(0, $db->table('jobs')->count());
        $db->beginTransaction();
        Notify::make('x')->title('T')->message('M')->recipients(new RecipientIdentity('account', '1'))->channels(['test'])->send();
        $db->commit();
        self::assertSame(1, app(Storage::class)->table('notifications')->count());
        self::assertSame(2, app(Storage::class)->table('deliveries')->where('channel', 'test')->count());
        self::assertSame(1, $db->table('jobs')->count());
    }

    public function test_sync_and_queue_disabled_tracking_work_without_inbox(): void
    {
        $channel = $this->setupDelivery(false);
        $channel->fail = false;
        foreach ([true, false] as $queue) {
            config(['notification-orchestrator.features.queue' => $queue]);
            $result = Notify::make('x')->title('T')->message('M')->recipients(new RecipientIdentity('account', '1'))->channels(['test'])->send();
            self::assertSame(0, $result->plannedQueueJobCount);
        }
        self::assertFalse(app(Storage::class)->available('notifications'));
        self::assertSame(4, app(Storage::class)->table('deliveries')->where('status', 'sent')->count());
        self::assertCount(4, $channel->calls);
    }

    public function test_transition_guard_and_confirmation_are_not_read_state(): void
    {
        $this->setupDelivery(false);
        app(DeliveryTransitionGuard::class)->assertAllowed(DeliveryStatus::SENT, DeliveryStatus::DELIVERED);
        $this->expectException(\LogicException::class);
        app(DeliveryTransitionGuard::class)->assertAllowed(DeliveryStatus::QUEUED, DeliveryStatus::DELIVERED);
    }
}
