<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Contracts\NotificationRepository;
use Elibardev\NotificationOrchestrator\Facades\Notify;
use Elibardev\NotificationOrchestrator\Persistence\NotificationQuery;
use Elibardev\NotificationOrchestrator\Persistence\PackageSchema;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\AuthenticatedAccount;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\RecordingBroadcaster;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Application;

class HttpRealtimeTest extends TestCase
{
    private RecordingBroadcaster $broadcast;

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('notification-orchestrator.features.broadcast', true);
        $app['config']->set('notification-orchestrator.features.preferences', true);
        $app['config']->set('broadcasting.default', 'recording');
        $app['config']->set('broadcasting.connections.recording', ['driver' => 'recording']);
        $this->broadcast = new RecordingBroadcaster;
        $app->make(BroadcastManager::class)->extend('recording', fn () => $this->broadcast);
    }

    protected function setUp(): void
    {
        parent::setUp();
        app(PackageSchema::class)->create('notifications');
        app(PackageSchema::class)->create('preferences');
    }

    private function owner(string $id): AuthenticatedAccount
    {
        return new AuthenticatedAccount(['id' => $id]);
    }

    public function test_api_auth_owner_isolation_and_personal_event_protocol(): void
    {
        $a = $this->owner('a');
        $b = $this->owner('b');
        $this->getJson('/api/notifications/bootstrap')->assertUnauthorized();
        $result = Notify::make('record.created')->title('Title')->message('Message')->recipients([$a, $b])->send();
        self::assertCount(2, $this->broadcast->messages);
        $this->actingAs($a);
        $data = $this->getJson('/api/notifications/bootstrap')->assertOk()->assertJsonPath('meta.unread_count', 1)->assertJsonPath('realtime.channel', 'notifications.account.a')->json();
        $id = $data['notifications'][0]['id'];
        self::assertNotSame($result->notificationId, $id);
        self::assertSame($id, $this->broadcast->messages[0]['payload']['notification']['id']);
        self::assertSame(['private-notifications.account.a'], $this->broadcast->messages[0]['channels']);
        $this->patchJson('/api/notifications/'.$id.'/read', ['user_id' => 'b'])->assertOk()->assertJsonPath('state.read', true)->assertJsonPath('meta.unread_count', 0);
        $this->patchJson('/api/notifications/'.$id.'/read')->assertOk()->assertJsonPath('meta.unread_count', 0);
        self::assertCount(3, $this->broadcast->messages);
        self::assertSame('notification.read', $this->broadcast->messages[2]['event']);
        $this->patchJson('/api/notifications/'.$id.'/unread')->assertOk()->assertJsonPath('meta.unread_count', 1);
        $this->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('changed', 1)->assertJsonPath('meta.unread_count', 0);
        $this->actingAs($b);
        $this->patchJson('/api/notifications/'.$id.'/read')->assertNotFound();
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('meta.unread_count', 1);
        $this->getJson('/api/notifications?limit=0')->assertUnprocessable();
        $this->getJson('/api/notifications?cursor=invalid')->assertUnprocessable();
    }

    public function test_preferences_inheritance_security_and_reset(): void
    {
        $this->actingAs($this->owner('a'));
        $this->putJson('/api/notifications/preferences', ['channel' => 'mail', 'enabled' => false, 'user_id' => 'b'])->assertOk()->assertJsonPath('source', 'user_global');
        $this->getJson('/api/notifications/preferences?channel=mail&type=x')->assertJsonPath('effective', false)->assertJsonPath('configured', null);
        $this->putJson('/api/notifications/preferences', ['channel' => 'broadcast', 'enabled' => false])->assertUnprocessable();
        $this->putJson('/api/notifications/preferences', ['channel' => 'unknown', 'enabled' => true])->assertUnprocessable();
        $this->actingAs($this->owner('b'));
        $this->getJson('/api/notifications/preferences?channel=mail')->assertJsonPath('source', 'package_default')->assertJsonPath('effective', true);
        $this->actingAs($this->owner('a'));
        $this->deleteJson('/api/notifications/preferences', ['channel' => 'mail'])->assertOk()->assertJsonPath('configured', null)->assertJsonPath('effective', true);
    }

    public function test_private_auth_rejects_other_owner_or_type(): void
    {
        $this->actingAs($this->owner('a'));
        $this->postJson('/api/notifications/broadcasting/auth', ['channel_name' => 'private-notifications.account.a', 'socket_id' => '1.2'])->assertOk()->assertJsonPath('authorized', true);
        $this->postJson('/api/notifications/broadcasting/auth', ['channel_name' => 'private-notifications.account.b', 'socket_id' => '1.2'])->assertForbidden();
        $this->postJson('/api/notifications/broadcasting/auth', ['channel_name' => 'private-notifications.admin.a', 'socket_id' => '1.2'])->assertForbidden();
    }

    public function test_broadcast_waits_for_commit_and_api_recovers_from_transport_failure(): void
    {
        $a = $this->owner('a');
        $db = app(Storage::class)->connection();
        $db->beginTransaction();
        Notify::make('x')->title('T')->message('M')->recipients($a)->send();
        self::assertCount(0, $this->broadcast->messages);
        $db->rollBack();
        self::assertCount(0, $this->broadcast->messages);
        $db->beginTransaction();
        Notify::make('x')->title('T')->message('M')->recipients($a)->send();
        $db->commit();
        self::assertCount(1, $this->broadcast->messages);
        $id = app(NotificationRepository::class)->paginateFor($a, new NotificationQuery)->items[0]->id;
        $db->beginTransaction();
        app(NotificationRepository::class)->markRead($a, $id);
        $db->rollBack();
        self::assertCount(1, $this->broadcast->messages);
        self::assertSame(1, app(NotificationRepository::class)->unreadCount($a));
        $this->broadcast->fail = true;
        $this->actingAs($a)->patchJson('/api/notifications/'.$id.'/read')->assertOk();
        $this->getJson('/api/notifications/bootstrap')->assertJsonPath('meta.unread_count',0)->assertJsonPath('notifications.0.state.read',true);
    }
}
