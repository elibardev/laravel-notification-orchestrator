<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Context\ContextTarget;
use Elibardev\NotificationOrchestrator\Contracts\AuthenticatedNotifiableResolver;
use Elibardev\NotificationOrchestrator\Contracts\MqttDriver;
use Elibardev\NotificationOrchestrator\Devices\DeviceRepository;
use Elibardev\NotificationOrchestrator\Facades\Notify;
use Elibardev\NotificationOrchestrator\Mail\OrchestratedMail;
use Elibardev\NotificationOrchestrator\Persistence\PackageSchema;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Elibardev\NotificationOrchestrator\Push\PushDriverRegistry;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\AuthenticatedAccount;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\FakeMqttDriver;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\FakePushDriver;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\RecordingBroadcaster;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Mail;

class ExternalIntegrationTest extends TestCase
{
    private RecordingBroadcaster $broadcast;

    private FakePushDriver $push;

    private FakeMqttDriver $mqtt;

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        foreach (['devices', 'push', 'mail', 'mqtt', 'broadcast', 'delivery_tracking', 'preferences'] as $feature) {
            $app['config']->set('notification-orchestrator.features.'.$feature, true);
        }
        $app['config']->set('notification-orchestrator.push.default_driver', 'fake');
        $app['config']->set('notification-orchestrator.queue.backoff', 0);
        $app['config']->set('broadcasting.default', 'recording');
        $app['config']->set('broadcasting.connections.recording', ['driver' => 'recording']);
        $this->broadcast = new RecordingBroadcaster;
        $app->make(BroadcastManager::class)->extend('recording', fn () => $this->broadcast);
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['notifications', 'preferences', 'deliveries', 'devices'] as $table) {
            app(PackageSchema::class)->create($table);
        }
        $this->push = new FakePushDriver;
        $this->mqtt = new FakeMqttDriver;
        app(PushDriverRegistry::class)->register('fake', $this->push);
        app()->instance(MqttDriver::class, $this->mqtt);
        Mail::fake();
    }

    public function test_all_channels_and_contexts_commit_together_with_distinct_personal_ids(): void
    {
        $a = new AuthenticatedAccount(['id' => 'a', 'email' => 'a@example.test']);
        $b = new AuthenticatedAccount(['id' => 'b', 'email' => 'b@example.test']);
        foreach (['a', 'b'] as $id) {
            app(DeviceRepository::class)->register(new RecipientIdentity('account', $id), ['driver' => 'fake', 'token' => 'token-'.$id]);
        }
        $db = app(Storage::class)->connection();
        $db->beginTransaction();
        $result = Notify::make('record.created')->title('T')->message('M')->recipients([$a, $b])->channels(['mail', 'push', 'mqtt'])
            ->broadcastTo('records.1')->contextTo(ContextTarget::mqtt('records/1'))->send();
        self::assertSame(2, $result->recipientCount);
        self::assertSame(2, $result->contextDeliveryCount);
        self::assertCount(0, $this->push->messages);
        self::assertCount(0, $this->broadcast->messages);
        Mail::assertNothingSent();
        $db->commit();
        Mail::assertSent(OrchestratedMail::class, 2);
        self::assertCount(2, $this->push->messages);
        self::assertCount(3, $this->mqtt->sent);
        self::assertCount(3, $this->broadcast->messages);
        self::assertNotSame($this->push->messages[0]->notificationId, $this->push->messages[1]->notificationId);
        $context = $this->broadcast->messages[2];
        self::assertSame(['private-records.1'], $context['channels']);
        self::assertSame('notification.context', $context['event']);
        self::assertSame($result->notificationId, $context['payload']['id']);
        self::assertArrayNotHasKey('read_at', $context['payload']);
        self::assertSame(6, app(Storage::class)->table('deliveries')->where('status', 'sent')->count());
        self::assertSame(2, app(Storage::class)->table('notifications')->whereNull('read_at')->count());
        $db->beginTransaction();
        Notify::make('rollback')->title('T')->message('M')->recipients($a)->channels(['mail', 'push', 'mqtt'])->broadcastTo('records.2')->send();
        $db->rollBack();
        self::assertCount(3, $this->broadcast->messages);
        self::assertCount(2, $this->push->messages);
        Mail::assertSentCount(2);
    }

    public function test_real_database_worker_refuses_reassigned_managed_destination(): void
    {
        config(['queue.default' => 'database']);
        $db = app(Storage::class)->connection();
        $db->getSchemaBuilder()->create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        $a = new RecipientIdentity('account', 'a');
        $b = new RecipientIdentity('account', 'b');
        app(DeviceRepository::class)->register($a, ['driver' => 'fake', 'token' => 'rotated-device']);
        Notify::make('x')->title('T')->message('M')->recipients($a)->channels(['push'])->send();
        // Structural broadcast also queues one job. Drain it before the optional push.
        self::assertSame(2, $db->table('jobs')->count());
        app(DeviceRepository::class)->register($b, ['driver' => 'fake', 'token' => 'rotated-device']);
        $worker = app('queue.worker');
        $worker->runNextJob('database', 'notifications', new WorkerOptions(sleep: 0, maxTries: 3));
        $worker->runNextJob('database', 'notifications', new WorkerOptions(sleep: 0, maxTries: 3));
        self::assertCount(0, $this->push->messages);
        self::assertSame(1, app(Storage::class)->table('deliveries')->where('channel', 'push')->where('status', 'failed')->count());
        self::assertSame(1, $db->table('jobs')->count());
        self::assertSame(1, app(Storage::class)->table('notifications')->whereNull('read_at')->count());
    }

    public function test_custom_authenticated_owner_is_shared_by_api_and_private_channel_auth(): void
    {
        $this->actingAs(new AuthenticatedAccount(['id' => 'auth-id']));
        app()->instance(AuthenticatedNotifiableResolver::class, new class implements AuthenticatedNotifiableResolver
        {
            public function resolve(Request $request): RecipientIdentity
            {
                return new RecipientIdentity('account', 'mapped-id');
            }
        });
        $this->getJson('/api/notifications/bootstrap')->assertOk()->assertJsonPath('realtime.channel', 'notifications.account.mapped-id');
        $this->postJson('/api/notifications/broadcasting/auth', ['channel_name' => 'private-notifications.account.mapped-id', 'socket_id' => '1.1'])->assertOk();
        $this->postJson('/api/notifications/broadcasting/auth', ['channel_name' => 'private-notifications.account.auth-id', 'socket_id' => '1.1'])->assertForbidden();
        $this->postJson('/api/notifications/broadcasting/auth', ['channel_name' => 'private-records.1', 'socket_id' => '1.1'])->assertForbidden();
    }

    public function test_representative_recipient_batch_produces_bounded_per_recipient_work(): void
    {
        $owners = array_map(fn ($id) => new RecipientIdentity('account', (string) $id), range(1, 150));
        $result = Notify::make('batch')->title('T')->message('M')->recipients($owners)->channels(['mqtt'])->send();
        self::assertSame(150, $result->recipientCount);
        self::assertSame(150, app(Storage::class)->table('notifications')->count());
        self::assertSame(150, app(Storage::class)->table('deliveries')->where('channel','mqtt')->where('status','sent')->count());
        self::assertCount(150,$this->mqtt->sent);
        self::assertCount(150,$this->broadcast->messages);
    }
}
