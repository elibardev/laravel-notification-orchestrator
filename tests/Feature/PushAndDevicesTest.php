<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Tests\Feature;

use Elibardev\NotificationOrchestrator\Contracts\DeliveryExecutor;
use Elibardev\NotificationOrchestrator\Contracts\FcmAccessTokenProvider;
use Elibardev\NotificationOrchestrator\Devices\DeviceRepository;
use Elibardev\NotificationOrchestrator\Exceptions\DeliveryExecutionException;
use Elibardev\NotificationOrchestrator\Facades\Notify;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\NotificationPayload;
use Elibardev\NotificationOrchestrator\Persistence\PackageSchema;
use Elibardev\NotificationOrchestrator\Persistence\Storage;
use Elibardev\NotificationOrchestrator\Planning\DeliveryPlanner;
use Elibardev\NotificationOrchestrator\Push\FcmDriver;
use Elibardev\NotificationOrchestrator\Push\GoogleAccessTokenProvider;
use Elibardev\NotificationOrchestrator\Push\PushDestination;
use Elibardev\NotificationOrchestrator\Push\PushDriverRegistry;
use Elibardev\NotificationOrchestrator\Push\PushMessage;
use Elibardev\NotificationOrchestrator\Recipients\RecipientIdentity;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\ExternalPushResolver;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\FakeFcmToken;
use Elibardev\NotificationOrchestrator\Tests\Fixtures\FakePushDriver;
use Elibardev\NotificationOrchestrator\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PushAndDevicesTest extends TestCase
{
    public function test_google_auth_signs_service_account_request_and_caches_access_token(): void
    {
        $opensslConfig = $this->fixturePath.'/openssl.cnf';
        file_put_contents($opensslConfig, "[req]\ndistinguished_name=dn\n[dn]\n");
        $key = openssl_pkey_new(['config' => $opensslConfig, 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $pem, null, ['config' => $opensslConfig]));
        $file = $this->fixturePath.'/service-account.json';
        file_put_contents($file, json_encode(['type' => 'service_account', 'client_email' => 'fixture@example.test', 'private_key' => $pem], JSON_THROW_ON_ERROR));
        config(['notification-orchestrator.push.drivers.fcm.credentials' => $file]);
        Http::preventStrayRequests();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-access-token', 'expires_in' => 3600, 'token_type' => 'Bearer'])]);
        $tokens = app(GoogleAccessTokenProvider::class);
        $tokens->validateConfiguration();
        Http::assertNothingSent();
        self::assertSame('fake-access-token', $tokens->token());
        self::assertSame('fake-access-token', $tokens->token());
        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($key): bool {
            parse_str($request->body(), $form);
            if (! isset($form['assertion']) || ! is_string($form['assertion'])) {
                return false;
            }
            $jwt = explode('.', $form['assertion']);
            if (count($jwt) !== 3) {
                return false;
            }
            $details = openssl_pkey_get_details($key);
            if ($details === false) {
                return false;
            }
            $claims = json_decode(base64_decode(strtr($jwt[1], '-_', '+/')), true);

            return $request->method() === 'POST' && $claims['scope'] === 'https://www.googleapis.com/auth/firebase.messaging'
                && openssl_verify($jwt[0].'.'.$jwt[1], base64_decode(strtr($jwt[2], '-_', '+/')), $details['key'], OPENSSL_ALGO_SHA256) === 1;
        });
    }

    public function test_temporary_fcm_failure_does_not_invalidate_and_large_payload_never_reaches_provider(): void
    {
        app()->bind(FcmAccessTokenProvider::class, FakeFcmToken::class);
        config(['notification-orchestrator.push.drivers.fcm.project_id' => 'test-project']);
        Http::preventStrayRequests();
        Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503)]);
        $driver = app(FcmDriver::class);
        $message = new PushMessage(new NotificationPayload('id', new NotificationContext('x', 'T', 'M')));
        $result = $driver->send(new PushDestination('fake-token', 'fcm'), $message);
        self::assertFalse($result->accepted);
        self::assertFalse($result->invalidDestination);
        $large = new PushMessage(new NotificationPayload('id', new NotificationContext('x', 'T', str_repeat('M', 5000))));
        self::assertFalse($driver->send(new PushDestination('fake-token', 'fcm'), $large)->accepted);
        Http::assertSentCount(1);
    }

    private function managed(): FakePushDriver
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'notification-orchestrator.features.devices' => true,
            'notification-orchestrator.features.push' => true, 'notification-orchestrator.push.default_driver' => 'fake']);
        $driver = new FakePushDriver;
        app(PushDriverRegistry::class)->register('fake', $driver);
        app(PackageSchema::class)->create('devices');
        app(PackageSchema::class)->create('notifications');

        return $driver;
    }

    public function test_rotation_reassignment_encryption_and_stale_plan_are_safe(): void
    {
        $driver = $this->managed();
        $a = new RecipientIdentity('account', 'a');
        $b = new RecipientIdentity('account', 'b');
        $repo = app(DeviceRepository::class);
        $install = (string) Str::uuid();
        $device = $repo->register($a, ['driver' => 'fake', 'token' => 'PRIVATE-TOKEN', 'device_identifier' => $install]);
        $raw = app(Storage::class)->table('devices')->first();
        self::assertNotNull($raw);
        self::assertNotSame('PRIVATE-TOKEN', $raw->token);
        self::assertSame(hash('sha256', 'PRIVATE-TOKEN'), $raw->token_hash);
        self::assertArrayNotHasKey('token', $device);
        $plan = app(DeliveryPlanner::class)->plan(new NotificationContext('x', 'T', 'M'), [$a], requested: ['push']);
        $rotated = $repo->register($a, ['driver' => 'fake', 'token' => 'NEW-TOKEN', 'device_identifier' => $install]);
        self::assertSame($device['id'], $rotated['id']);
        $repo->register($b, ['driver' => 'fake', 'token' => 'NEW-TOKEN', 'device_identifier' => $install]);
        try {
            app(DeliveryExecutor::class)->execute($plan);
            self::fail('Stale destination must fail.');
        } catch (DeliveryExecutionException) {
        }
        self::assertCount(0, $driver->messages);
        self::assertCount(0, $repo->allFor($a));
        self::assertCount(1, $repo->allFor($b));
    }

    public function test_provider_invalid_token_is_disabled_and_never_marked_read(): void
    {
        $driver = $this->managed();
        $driver->invalid = true;
        $owner = new RecipientIdentity('account', 'a');
        $device = app(DeviceRepository::class)->register($owner, ['driver' => 'fake', 'token' => 'bad-token']);
        try {
            Notify::make('x')->title('T')->message('M')->recipients($owner)->channels(['push'])->send();
            self::fail('Expected provider rejection.');
        } catch (DeliveryExecutionException) {
        }
        $stored = app(DeviceRepository::class)->findFor($owner, $device['id']);
        self::assertFalse($stored['enabled']);
        self::assertNotNull($stored['invalidated_at']);
        self::assertSame(1, app(Storage::class)->table('notifications')->whereNull('read_at')->count());
    }

    public function test_external_resolver_works_without_device_or_inbox_tables(): void
    {
        config(['notification-orchestrator.features.database' => false, 'notification-orchestrator.features.api' => false, 'notification-orchestrator.features.push' => true,
            'notification-orchestrator.push.default_driver' => 'fake', 'notification-orchestrator.push.destination_resolver' => ExternalPushResolver::class]);
        $driver = new FakePushDriver;
        app(PushDriverRegistry::class)->register('fake', $driver);
        Notify::make('x')->title('T')->message('M')->data(['secret' => 'not-for-push'])->recipients(new RecipientIdentity('account', 'a'))->channels(['push'])->send();
        self::assertCount(1, $driver->messages);
        self::assertNull($driver->messages[0]->notificationId);
        self::assertArrayNotHasKey('notification_id', $driver->messages[0]->data());
        self::assertArrayNotHasKey('secret', $driver->messages[0]->data());
        self::assertFalse(app(Storage::class)->available('devices'));
    }

    public function test_fcm_http_v1_projection_and_unregistered_error(): void
    {
        app()->bind(FcmAccessTokenProvider::class, FakeFcmToken::class);
        config(['notification-orchestrator.push.drivers.fcm.project_id' => 'test-project']);
        Http::preventStrayRequests();
        Http::fake(['fcm.googleapis.com/*' => Http::sequence()->push(['name' => 'projects/test/messages/1'])->push(['error' => ['details' => [
            ['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]], 404)]);
        $payload = new NotificationPayload((string) Str::uuid(), new NotificationContext('x', 'Title', 'Body', data: ['private' => 'excluded']));
        $message = new PushMessage($payload, 'personal-id');
        $driver = app(FcmDriver::class);
        $driver->validateConfiguration();
        self::assertTrue($driver->send(new PushDestination('fcm-token', 'fcm'), $message)->accepted);
        self::assertTrue($driver->send(new PushDestination('fcm-token', 'fcm'), $message)->invalidDestination);
        Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/test-project/messages:send'
            && $request['message']['data']['notification_id'] === 'personal-id' && ! isset($request['message']['data']['private']) && $request->hasHeader('Authorization', 'Bearer test-oauth-token'));
    }
}
