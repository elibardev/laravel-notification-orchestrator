<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Channels\SkipReason;
use Elibardev\NotificationOrchestrator\Context\ContextTarget;
use Elibardev\NotificationOrchestrator\Contracts\MqttDriver;
use Elibardev\NotificationOrchestrator\Contracts\PreferenceRepository;
use Elibardev\NotificationOrchestrator\Exceptions\DeliveryExecutionException;
use Elibardev\NotificationOrchestrator\Facades\Notify;
use Elibardev\NotificationOrchestrator\Mail\OrchestratedMail;
use Elibardev\NotificationOrchestrator\Mqtt\MqttClientFactory;
use Elibardev\NotificationOrchestrator\Mqtt\PhpMqttDriver;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\NotificationPayload;
use Elibardev\NotificationOrchestrator\Persistence\PackageSchema;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\ActiveContextPolicy;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\AuthenticatedAccount;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\FakeMqttDriver;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Contracts\Repository as MqttRepository;
use PhpMqtt\Client\PublishedMessage;

class MailMqttPresenceTest extends TestCase
{
    public function test_mail_uses_laravel_routing_and_safe_html_without_read_side_effects(): void
    {
        config(['notification-orchestrator.features.mail' => true]);
        app(PackageSchema::class)->create('notifications');
        Mail::fake();
        $owner = new AuthenticatedAccount(['id' => 'mail-owner', 'email' => 'test@example.test']);
        Notify::make('x')->title('Title')->message('<script>unsafe</script>')->recipients($owner)->channels(['mail'])->send();
        Mail::assertSent(OrchestratedMail::class, fn ($mail) => $mail->hasTo('test@example.test'));
        self::assertSame(1, app(Storage::class)->table('notifications')->whereNull('read_at')->count());
        $payload = new NotificationPayload('id', new NotificationContext('x', 'Title', '<script>unsafe</script>'));
        self::assertStringContainsString('&lt;script&gt;', (new OrchestratedMail($payload))->render());
    }

    public function test_personal_preferences_do_not_suppress_context_and_publish_waits_for_commit(): void
    {
        config(['notification-orchestrator.features.mqtt' => true, 'notification-orchestrator.features.preferences' => true]);
        app(PackageSchema::class)->create('notifications');
        app(PackageSchema::class)->create('preferences');
        $driver = new FakeMqttDriver;
        app()->instance(MqttDriver::class, $driver);
        $owner = new RecipientIdentity('account', 'one');
        app(PreferenceRepository::class)->set($owner, null, 'mqtt', false);
        $db = app(Storage::class)->connection();
        $db->beginTransaction();
        $result = Notify::make('x')->title('T')->message('M')->recipients($owner)->channels(['mqtt'])->contextTo(ContextTarget::mqtt('records/1'))->send();
        self::assertCount(0, $driver->sent);
        $db->rollBack();
        self::assertCount(0, $driver->sent);
        $db->beginTransaction();
        $result = Notify::make('x')->title('T')->message('M')->recipients($owner)->channels(['mqtt'])->contextTo(ContextTarget::mqtt('records/1'))->send();
        $db->commit();
        self::assertCount(1, $driver->sent);
        self::assertSame('records/1', $driver->sent[0]['topic']);
        self::assertSame(1, $driver->sent[0]['qos']);
        self::assertFalse($driver->sent[0]['retain']);
        self::assertSame($result->notificationId, json_decode($driver->sent[0]['payload'], true)['id']);
        app(PreferenceRepository::class)->delete($owner, null, 'mqtt');
        Notify::make('x')->title('T')->message('M')->recipients($owner)->channels(['mqtt'])->send();
        self::assertSame('notifications/account/one', $driver->sent[1]['topic']);
    }

    public function test_presence_only_suppresses_optional_channels_and_is_exercised_by_public_fake(): void
    {
        config(['notification-orchestrator.features.presence' => true, 'notification-orchestrator.presence.policy' => ActiveContextPolicy::class,
            'notification-orchestrator.features.broadcast' => true, 'broadcasting.default' => 'log', 'notification-orchestrator.features.mqtt' => true]);
        app()->instance(MqttDriver::class, new FakeMqttDriver);
        Notify::fake();
        Notify::make('x')->title('T')->message('M')->recipients(new RecipientIdentity('account', 'one'))->channels(['mqtt'])->broadcastTo('records.1')->send();
        $owner = new RecipientIdentity('account', 'one');
        Notify::assertChannelSkipped($owner, 'mqtt', SkipReason::PRESENCE);
        Notify::assertChannelPlanned($owner, 'database');
        Notify::assertChannelPlanned($owner, 'broadcast');
        Notify::assertBroadcastTo('records.1');
    }

    public function test_mqtt_adapter_waits_for_qos_one_ack_and_never_claims_timeout_as_success(): void
    {
        config(['notification-orchestrator.mqtt.host' => 'localhost']);
        $client = $this->createMock(MqttClient::class);
        $queue = null;
        $factory = $this->createMock(MqttClientFactory::class);
        $factory->method('make')->willReturnCallback(function (MqttRepository $messages) use (&$queue, $client) {
            $queue = $messages;

            return $client;
        });
        app()->instance(MqttClientFactory::class, $factory);
        $client->expects(self::once())->method('connect');
        $client->expects(self::once())->method('publish')->with('records/1', '{}', 1, false)->willReturnCallback(function () use (&$queue) {
            self::assertNotNull($queue);
            $queue->addPendingOutgoingMessage(new PublishedMessage(1, 'records/1', '{}', 1, false));
        });
        $client->expects(self::once())->method('loop')->with(true, true, 10);
        $client->method('isConnected')->willReturn(true);
        $client->expects(self::once())->method('disconnect');
        $this->expectException(DeliveryExecutionException::class);
        app(PhpMqttDriver::class)->publish('records/1', '{}');
    }

    public function test_mqtt_qos_one_accepts_confirmed_publish_with_tls_verification(): void
    {
        config(['notification-orchestrator.mqtt.host' => 'localhost', 'notification-orchestrator.mqtt.tls' => true]);
        $client = $this->createMock(MqttClient::class);
        $queue = null;
        $factory = $this->createMock(MqttClientFactory::class);
        $factory->method('make')->willReturnCallback(function (MqttRepository $messages) use (&$queue, $client) {
            $queue = $messages;

            return $client;
        });
        app()->instance(MqttClientFactory::class, $factory);
        $client->expects(self::once())->method('connect')->with(self::callback(fn ($settings) => $settings->shouldUseTls() && $settings->shouldTlsVerifyPeer() && $settings->shouldTlsVerifyPeerName()), true);
        $client->expects(self::once())->method('publish')->willReturnCallback(function () use (&$queue) {
            self::assertNotNull($queue);
            $queue->addPendingOutgoingMessage(new PublishedMessage(1, 'records/1', '{}', 1, false));
        });
        $client->expects(self::once())->method('loop')->willReturnCallback(function () use (&$queue) {
            self::assertNotNull($queue);
            $queue->removePendingOutgoingMessage(1);
        });
        app(PhpMqttDriver::class)->publish('records/1', '{}');
    }

    public function test_mqtt_qos_zero_does_not_wait_for_ack(): void
    {
        config(['notification-orchestrator.mqtt.host' => 'localhost']);
        $client = $this->createMock(MqttClient::class);
        $factory = $this->createMock(MqttClientFactory::class);
        $factory->method('make')->willReturn($client);
        app()->instance(MqttClientFactory::class, $factory);
        $client->expects(self::once())->method('publish')->with('records/1', '{}', 0, false);
        $client->expects(self::never())->method('loop');
        app(PhpMqttDriver::class)->publish('records/1','{}',0,false);
    }
}
