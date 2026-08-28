<?php

declare(strict_types=1);

use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Context\ContextDeliveryPlan;
use Elibardev\NotificationOrchestrator\Context\ContextTransportRegistry;
use Elibardev\NotificationOrchestrator\Contracts\MqttDriver;
use Elibardev\NotificationOrchestrator\Mail\OrchestratedMail;
use Elibardev\NotificationOrchestrator\NotificationContext;
use Elibardev\NotificationOrchestrator\NotificationPayload;
use Elibardev\NotificationOrchestrator\Push\FcmDriver;
use Elibardev\NotificationOrchestrator\Push\PushDestination;
use Elibardev\NotificationOrchestrator\Push\PushMessage;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Support\Str;

// Deliberately outside the default suite. Never run against production.
if (PHP_SAPI !== 'cli' || getenv('NOTIFICATIONS_LIVE_ACK') !== 'approved-test-destination'
    || ! isset($argv[1],$argv[2]) || ! in_array($argv[2], ['mail', 'fcm', 'mqtt', 'broadcast'], true)) {
    fwrite(STDERR, "Refused. Explicit test-destination approval, Laravel app path and one profile are required.\n");
    exit(2);
}
try {
    $root = realpath($argv[1]);
    if ($root === false || ! is_file($root.'/vendor/autoload.php') || ! is_file($root.'/bootstrap/app.php')) {
        throw new RuntimeException;
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if ($app->environment('production')) {
        throw new RuntimeException;
    }
    $profile = $argv[2];
    $destination = getenv('NOTIFICATIONS_LIVE_DESTINATION');
    if (! is_string($destination) || trim($destination) === '') {
        throw new RuntimeException;
    }
    $config = $app->make(Configuration::class);
    if (! $config->enabled($profile === 'fcm' ? 'push' : $profile)) {
        throw new RuntimeException;
    }
    $payload = new NotificationPayload((string) Str::uuid(), new NotificationContext('integration.test', 'Integration test', 'Approved test destination only.'));
    if ($profile === 'mail') {
        if (! filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException;
        }
        $app->make(Factory::class)->mailer($config->get('mail.mailer'))->to($destination)->send(new OrchestratedMail($payload));
    } elseif ($profile === 'fcm') {
        $driver = $app->make(FcmDriver::class);
        $driver->validateConfiguration();
        if (! $driver->send(new PushDestination($destination, 'fcm'), new PushMessage($payload))->accepted) {
            throw new RuntimeException;
        }
    } elseif ($profile === 'mqtt') {
        $app->make(MqttDriver::class)->publish($destination, json_encode($payload->toArray(), JSON_THROW_ON_ERROR), 1, false);
    } else {
        $transport = $app->make(ContextTransportRegistry::class)->get('broadcast');
        if ($transport === null) {
            throw new RuntimeException;
        }
        $transport->validateConfiguration();
        $result = $transport->publish(new ContextDeliveryPlan('broadcast', $destination, $payload, [], (string) Str::uuid()));
        if ($result->status !== DeliveryStatus::SENT) {
            throw new RuntimeException;
        }
    }
    fwrite(STDOUT, "Transport accepted the test message. Subscriber rendering, delivery and read are NOT verified.\n");
} catch (Throwable) {
    fwrite(STDERR, "Live profile failed. No credentials or provider exception details are printed.\n");
    exit(1);
}
