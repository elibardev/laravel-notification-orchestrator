<?php

declare(strict_types=1);
use Elibardev\NotificationOrchestrator\Devices\DatabasePushDestinationResolver;
use Elibardev\NotificationOrchestrator\Mail\MailDestinationResolver;
use Elibardev\NotificationOrchestrator\Mqtt\MqttDestinationResolver;
use Elibardev\NotificationOrchestrator\Planning\PersonalBroadcastDestinationResolver;
use Elibardev\NotificationOrchestrator\Push\PushChannelDestinationResolver;
use Elibardev\NotificationOrchestrator\Recipients\DefaultRecipientNormalizer;
use Elibardev\NotificationOrchestrator\Support\DefaultReferenceNormalizer;

// Features describe capabilities; delivery providers arrive in later phases.
return [
    'features' => [
        'database' => true,
        'queue' => true,
        'broadcast' => false,
        'preferences' => false,
        'devices' => false,
        'push' => false,
        'mail' => false,
        'mqtt' => false,
        'delivery_tracking' => false,
        'presence' => false,
        'api' => true,
        'blade' => false,
    ],

    'database' => [
        'connection' => null,
        'table_prefix' => env('NOTIFICATIONS_TABLE_PREFIX', 'notify_'),
        'tables' => [
            'notifications' => null,
            'preferences' => null,
            'devices' => null,
            'deliveries' => null,
        ],
    ],

    'queue' => [
        'connection' => env('NOTIFICATIONS_QUEUE_CONNECTION'),
        'queue' => env('NOTIFICATIONS_QUEUE', 'notifications'),
        'tries' => 3,
        'backoff' => 5,
    ],

    'broadcast' => [
        'connection' => env('NOTIFICATIONS_BROADCAST_CONNECTION'),
        'queue' => env('NOTIFICATIONS_BROADCAST_QUEUE'),
        'personal_channel' => 'notifications.{notifiable}.{id}',
    ],

    'api' => [
        'prefix' => 'api/notifications',
        'middleware' => ['web', 'auth'],
        'name_prefix' => 'notifications.',
    ],

    'preferences' => [
        'default' => true,
        'defaults' => [],
        'types' => [],
    ],

    'channels' => [
        'defaults' => [],
        'types' => [],
        'destinations' => [
            'broadcast' => PersonalBroadcastDestinationResolver::class,
            'mail' => MailDestinationResolver::class,
            'push' => PushChannelDestinationResolver::class,
            'mqtt' => MqttDestinationResolver::class,
        ],
    ],

    'recipients' => [
        'normalizer' => DefaultRecipientNormalizer::class,
        'filters' => [],
    ],

    'references' => [
        'normalizer' => DefaultReferenceNormalizer::class,
    ],

    'push' => [
        'default_driver' => 'fcm',
        'destination_resolver' => DatabasePushDestinationResolver::class,
        'drivers' => [
            'fcm' => ['project_id' => env('NOTIFICATIONS_FCM_PROJECT'), 'credentials' => env('NOTIFICATIONS_FCM_CREDENTIALS')],
        ],
    ],
    'mail' => ['mailer' => null],
    'devices' => ['prune_invalidated_after_days' => null],
    'presence' => ['policy' => null],
    'mqtt' => ['host' => env('NOTIFICATIONS_MQTT_HOST'), 'port' => 1883, 'tls' => false,
        'username' => env('NOTIFICATIONS_MQTT_USERNAME'), 'password' => env('NOTIFICATIONS_MQTT_PASSWORD'),
        'timeout' => 10, 'qos' => 1, 'retain' => false, 'personal_topic' => 'notifications/{notifiable}/{id}'],

    'delivery_tracking' => [
        'retention_days' => 90,
        'channels' => ['database' => false, 'broadcast' => false, 'mail' => true, 'push' => true, 'mqtt' => true],
        'record_skipped' => true,
    ],
    'retention' => ['notifications' => ['enabled' => false, 'days' => null, 'only_read' => true], 'chunk_size' => 500],
    'blade' => ['styles' => true],
];
