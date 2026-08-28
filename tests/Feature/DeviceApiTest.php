<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Persistence\PackageSchema;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\AuthenticatedAccount;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;

class DeviceApiTest extends TestCase
{
    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('notification-orchestrator.features.devices', true);
        $app['config']->set('notification-orchestrator.features.api', false);
        $app['config']->set('notification-orchestrator.features.database', false);
    }

    public function test_default_web_middleware_enforces_csrf_for_device_mutations(): void
    {
        app()->instance('env', 'local');
        $this->actingAs(new AuthenticatedAccount(['id' => 'one']));
        $this->postJson('/api/notifications/devices', ['driver' => 'fcm', 'platform' => 'ios', 'token' => 'fake'])->assertStatus(419);
        $this->withSession(['_token' => 'csrf-fixture'])->postJson('/api/notifications/devices', [
            '_token' => 'csrf-fixture', 'driver' => 'fcm', 'platform' => 'desktop', 'token' => 'fake',
        ])->assertOk();
    }

    public function test_invalid_encryption_and_presence_configuration_fail_safely(): void
    {
        config(['app.key' => 'SECRET-INVALID-KEY', 'notification-orchestrator.features.presence' => true, 'notification-orchestrator.presence.policy' => null]);
        $kernel = app(Kernel::class);
        self::assertSame(1, $kernel->call('notifications:status'));
        $output = $kernel->output();
        self::assertStringContainsString('encryption key', $output);
        self::assertStringContainsString('PresencePolicy', $output);
        self::assertStringNotContainsString('SECRET-INVALID-KEY', $output);
        config(['notification-orchestrator.features.devices' => false, 'notification-orchestrator.features.presence' => false,
            'notification-orchestrator.push.drivers.fcm.credentials' => 'missing-secret-file', 'notification-orchestrator.mqtt.host' => null]);
        self::assertSame(0, $kernel->call('notifications:status'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        app(PackageSchema::class)->create('devices');
    }

    public function test_device_endpoints_are_authenticated_owner_scoped_and_independent_of_inbox(): void
    {
        $this->postJson('/api/notifications/devices', [])->assertUnauthorized();
        $this->actingAs(new AuthenticatedAccount(['id' => 'one']));
        $device = $this->postJson('/api/notifications/devices', ['driver' => 'fcm', 'platform' => 'ios', 'token' => 'DEVICE-SECRET', 'device_identifier' => (string) Str::uuid(), 'notifiable_id' => 'other'])->assertOk()->json();
        self::assertArrayNotHasKey('token', $device);
        self::assertArrayNotHasKey('token_hash', $device);
        $this->getJson('/api/notifications/devices')->assertJsonCount(1, 'devices');
        $this->getJson('/api/notifications/bootstrap')->assertNotFound();
        $this->actingAs(new AuthenticatedAccount(['id' => 'two']));
        $this->patchJson('/api/notifications/devices/'.$device['id'], ['enabled' => false])->assertNotFound();
        $this->deleteJson('/api/notifications/devices/'.$device['id'])->assertNotFound();
        $this->getJson('/api/notifications/devices')->assertJsonCount(0, 'devices');
        $this->actingAs(new AuthenticatedAccount(['id' => 'one']));
        $this->deleteJson('/api/notifications/devices/'.$device['id'])->assertOk();
        self::assertSame(0, app(Storage::class)->table('devices')->where('enabled', true)->count());
        self::assertFalse(app(Storage::class)->available('notifications'));
    }

    public function test_invalid_device_pruning_is_opt_in_and_scoped(): void
    {
        $now = now();
        app(Storage::class)->table('devices')->insert(['id' => (string) Str::uuid(), 'notifiable_type' => 'account', 'notifiable_id' => 'one', 'driver' => 'fcm', 'platform' => 'ios',
            'token' => 'encrypted-test-data', 'token_hash' => hash('sha256', 'fake'), 'enabled' => false, 'invalidated_at' => $now->copy()->subDays(40), 'created_at' => $now, 'updated_at' => $now]);
        $kernel = app(Kernel::class);
        $kernel->call('notifications:prune', ['--only' => 'devices']);
        self::assertSame(1, app(Storage::class)->table('devices')->count());
        config(['notification-orchestrator.devices.prune_invalidated_after_days' => 30]);
        $kernel->call('notifications:prune', ['--only' => 'devices', '--dry-run' => true]);
        self::assertSame(1, app(Storage::class)->table('devices')->count());
        $kernel->call('notifications:prune', ['--only' => 'devices']);
        self::assertSame(0,app(Storage::class)->table('devices')->count());
    }
}
