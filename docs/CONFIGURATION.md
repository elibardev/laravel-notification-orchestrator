# Configuration Specification

## Implementation status

All three phases implement schema-aware map/list combination, strict validation,
diagnostics and capability/table resolution. The shipped config file and
[PHASE-2.md](PHASE-2.md)/[PHASE-3.md](PHASE-3.md) specify current defaults and adapter
keys. The public fake plans without sending but validates enabled adapters.

## Goals

Configuration must support feature activation, configurable table
prefixes, explicit per-table overrides, queue behavior, API routes,
broadcasting, push, preferences, retention and delivery tracking.

## Canonical configuration and activation

The configuration file is `config/notification-orchestrator.php`. Access it
only through the `notification-orchestrator.*` namespace, for example:

``` php
config('notification-orchestrator.features.push');
```

`features.<name>` is the sole activation switch for each registered capability.
Module sections contain behavior/provider settings, not duplicate `enabled`
switches. Old module activation keys are invalid and have no aliases.
Core remains always enabled. Route names, channel names, commands and existing
`NOTIFICATIONS_*` environment variables keep their independent names.

Disabled features do not require provider credentials or instantiate their
providers. Enabled features validate dependencies without silently activating
other features. Normal execution fails fast; status uses diagnostic validation.
Push with an external resolver does not require managed devices.

Combine schema-defined maps recursively and replace lists completely. Preserve
explicit `false`, `null` and empty lists where valid. Empty maps supply no
overrides. For example, a partial feature map retains other defaults, but an
API middleware list replaces the default list entirely. A requested-channel
list of `[]` requests no optional channels.

Centralize these rules; shallow default merging alone is insufficient. Keep
configuration cacheable and use resolver class names, not configuration closures.
`enabled` in device records, preferences and retention policies is not a
duplicate feature switch.

See [ADR-0033](adr/0033-canonical-feature-configuration.md).

## Database connection

The default and recommended deployment uses the **same database
connection as the consuming Laravel application**.

The package does not require or imply a separate notification database.

``` php
'database' => [
    'connection' => null,
]
```

`null` means: use Laravel's default application database connection.

A separate connection may be considered later as an advanced override,
but it is not part of the architectural requirement for v0.1.

## Configuration example

``` php
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
    ],

    'push' => [
        'default_driver' => 'fcm',
        'drivers' => [
            'fcm' => [],
        ],
    ],

    'delivery_tracking' => [
        'retention_days' => 90,
    ],
];
```

## Table naming

The global prefix is configurable.

``` env
NOTIFICATIONS_TABLE_PREFIX=notify_
```

With no explicit overrides:

``` text
notify_notifications
notify_preferences
notify_devices
notify_deliveries
```

An explicit table override always wins.

``` php
'tables' => [
    'notifications' => 'system_notifications',
]
```

Result:

``` text
system_notifications
notify_preferences
notify_devices
notify_deliveries
```

All naming must be resolved through one internal service:

``` php
TableNameResolver::for('notifications');
```

Models, repositories, migrations and queries must never concatenate
prefixes independently.

## Queue

The package is queue-backend agnostic.

The reference initial deployment is:

``` env
QUEUE_CONNECTION=database
```

The application's normal Laravel `jobs` and `failed_jobs` tables remain
application infrastructure and use the same application database unless
the application itself configures otherwise.

Redis/Valkey is not required by the orchestrator.

## Channel classification

The initial channel model is:

``` text
Structural:
- database
- broadcast

Optional / preference-aware:
- push
- mail
- mqtt
```

Structural channels are application infrastructure. Users cannot disable
them through notification preferences.

Optional channels are resolved through event selection and effective
user preferences.

## MQTT channel

MQTT is an optional recipient-delivery channel. It is independent from
`broadcastTo()` and does not replace mobile push.

Reference broker for the initial design:

``` text
Eclipse Mosquitto
```

The package must depend on MQTT semantics rather than Mosquitto-specific
behavior so other MQTT-compatible brokers can be supported later.

Proposed configuration:

``` php
'mqtt' => [
    'connection' => 'default',
    'topic' => 'notifications/{notifiable}/{id}',
    'qos' => 1,
    'retain' => false,
],
```

The exact MQTT client dependency will be selected during implementation
and must remain behind an internal driver/transport contract.

`push` remains a distinct channel intended for operating-system mobile
notifications through providers such as FCM/APNs.

`broadcastTo()` remains contextual realtime delivery through Laravel
Broadcasting and is not an MQTT provider selector.
