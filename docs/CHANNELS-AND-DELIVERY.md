# Channels and Delivery

## Status

Draft v0.1.

## Objective

Define how notification channels are registered, validated, resolved,
planned and executed without coupling the orchestrator core to specific
providers.

## Channel taxonomy

``` text
Structural channels
- database
- broadcast

Optional recipient channels
- push
- mail
- mqtt
- future custom channels

Context realtime
- broadcastTo(...)
```

Structural channels are not user-configurable.

Optional channels are preference-aware.

`broadcastTo()` is contextual Laravel Broadcasting and is outside
recipient preference resolution.

## ChannelKind

``` php
enum ChannelKind: string
{
    case STRUCTURAL = 'structural';
    case OPTIONAL = 'optional';
}
```

## Channel registry

The package maintains a registry of known channels.

Built-in channels:

``` text
database
broadcast
push
mail
mqtt
```

Applications or third-party packages may register additional channels.

Proposed API:

``` php
NotificationChannels::register(
    'sms',
    SmsChannel::class
);
```

or through a manager-style extension API:

``` php
NotificationChannels::extend(
    'teams',
    fn ($app) => new TeamsChannel(...)
);
```

Exact public method names remain provisional during `0.x`.

## Channel state

A registered channel has three relevant states:

``` text
registered
enabled
healthy
```

### registered

The orchestrator knows the channel implementation.

### enabled

Application configuration enables the feature/channel.

### healthy

The channel is correctly configured and, where meaningful, its
infrastructure health check succeeds.

## Fail-fast configuration policy

Enabled channels must have valid configuration.

Examples:

``` text
MQTT enabled but broker host missing
-> configuration exception

Push enabled but FCM credentials missing
-> configuration exception

Unknown configured channel
-> unsupported channel exception
```

Invalid enabled-channel configuration must not silently degrade into
`unavailable`.

Disabled channels do not require provider configuration validation.

## Runtime delivery failures

A valid configuration does not guarantee runtime delivery.

Examples:

``` text
MQTT broker temporarily unreachable
FCM request timeout
SMTP temporarily unavailable
```

These are operational delivery failures, not configuration failures.

They should:

-   respect Laravel queue retry behavior;
-   produce a failed delivery result;
-   optionally enter delivery tracking;
-   not prevent unrelated channels from being processed where execution
    isolation permits.

## NotificationChannel contract

Initial conceptual contract:

``` php
interface NotificationChannel
{
    public function name(): string;

    public function kind(): ChannelKind;

    public function validateConfiguration(): void;

    public function health(): ChannelHealth;

    public function send(
        object $recipient,
        NotificationPayload $payload
    ): DeliveryResult;
}
```

Implementation may separate configuration/health/send responsibilities
into smaller interfaces if testing or design quality benefits.

## Destination resolution

Channels may require one or multiple recipient-specific destinations.

Examples:

``` text
database
-> recipient model

broadcast
-> personal notification channel

push
-> registered device tokens

mail
-> routeNotificationForMail() / application route

mqtt
-> configured recipient topic(s)
```

Proposed contract:

``` php
interface ChannelDestinationResolver
{
    public function resolve(
        object $recipient,
        NotificationContext $context
    ): iterable;
}
```

A missing destination is not necessarily a configuration error.

Example:

``` text
Push channel healthy
User has no registered device
-> delivery skipped: no_destination
```

## Delivery planner

The `DeliveryPlanner` builds a recipient-specific plan before execution.

Inputs:

``` text
NotificationContext
Recipient
ChannelRegistry
System configuration
Event-requested optional channels
PreferenceResolver
DestinationResolver(s)
```

Output:

``` php
DeliveryPlan
```

## Delivery resolution

Structural channels:

``` text
feature enabled
-> include
```

Optional channels:

``` text
registered
AND enabled
AND event requested
AND effective user preference
AND destination exists
-> include
```

## DeliveryPlan

Conceptual structure:

``` php
DeliveryPlan(
    recipient: $user,
    channels: [
        ChannelPlan::deliver('database'),
        ChannelPlan::deliver('broadcast'),
        ChannelPlan::deliver('push'),
        ChannelPlan::skip(
            'mail',
            SkipReason::USER_PREFERENCE
        ),
        ChannelPlan::skip(
            'mqtt',
            SkipReason::NO_DESTINATION
        ),
    ]
);
```

The plan should preserve skipped channels and reasons for diagnostics
and optional delivery tracking.

## SkipReason

Initial candidates:

``` text
not_requested
disabled
user_preference
no_destination
presence
```

The presence reason is specified by ADR-0036; unknown requested channels throw.
`unavailable` should not normally represent invalid configuration
because enabled invalid configuration fails fast.

A runtime infrastructure outage becomes a delivery failure rather than a
planning skip.

## DeliveryResult

Proposed provider-neutral result:

``` text
success
failed
skipped
```

Potential metadata:

``` text
channel
provider
provider_reference
attempt
error_code
error_message
timestamps
```

## Execution isolation

Where practical, each optional external channel should execute
independently so a provider outage does not prevent other delivery
channels.

Queue implementation details remain Laravel-owned.

## Extensibility

A custom channel should be able to contribute:

-   channel name;
-   kind;
-   configuration validation;
-   health check;
-   destination resolver;
-   send implementation;
-   diagnostics metadata.

The core must not require modification to add a properly registered
third-party channel.

## Detailed specifications

This overview is complemented by:

-   `CHANNEL-REGISTRY.md`
-   `DELIVERY-PLAN.md`

Those documents define the implementation-oriented contracts, planning
rules and execution boundaries.
