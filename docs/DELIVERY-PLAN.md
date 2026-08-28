# Delivery Plan Specification

## Status

Draft v0.1 --- implementation-oriented design.

## Purpose

`DeliveryPlan` is the immutable result of deciding how a single
notification should be delivered to a single recipient.

The planner does not deliver anything.

It runs during `send()` and has no inbox/tracking/queue/publication side
effects. Recipient selection, preferences and destinations are snapshotted once;
execution after commit and retries reuse the prepared plan. See
[ADR-0034](adr/0034-planning-and-after-commit-execution.md).

It answers:

``` text
What should be attempted?
What should be skipped?
Why?
To which destinations?
Using which normalized payload?
```

## Core pipeline

``` text
NotificationContext
        +
Recipient
        +
Event channel selection
        |
        v
DeliveryPlanner
        |
        +-- ChannelRegistry
        +-- PreferenceResolver
        +-- DestinationResolvers
        +-- Feature configuration
        |
        v
DeliveryPlan
        |
        v
DeliveryExecutor
```

## Per-recipient planning

A separate plan is generated for each recipient because:

-   preferences differ;
-   mail addresses differ;
-   push devices differ;
-   MQTT topics may differ;
-   a recipient may have no valid destination for one channel.

Example:

``` text
Notification recipients:
Juan
María
Pedro

Result:
DeliveryPlan(Juan)
DeliveryPlan(María)
DeliveryPlan(Pedro)
```

## Structural channel rules

### database

If the database feature is enabled:

``` text
database = deliver
```

It is not affected by user preferences.

If the application globally disables database persistence, it is absent
from the plan.

### broadcast

If realtime broadcast is enabled:

``` text
broadcast = deliver
```

It is not affected by user preferences.

If a valid personal broadcast destination cannot be derived, this is an
implementation/configuration issue rather than a user preference.

## Optional channel rules

For each optional channel:

``` text
registered?
    no -> error if explicitly requested

enabled?
    no -> skip: disabled

requested by event?
    no -> skip: not_requested

effective preference?
    false -> skip: user_preference

destination required?
    yes:
        destination exists?
            no -> skip: no_destination

otherwise:
    deliver
```

Configuration validity is checked before planning.

An enabled invalid channel must fail fast.

## Event channel selection

Normal package defaults should avoid forcing developers to enumerate
every optional channel on every notification.

Proposed hierarchy:

``` text
1. Explicit channels declared by notification builder/context
2. Per-notification-type channel defaults
3. Global optional channel defaults
```

Thus:

``` php
Notify::make('incident.created')
    ->title(...)
    ->recipients(...)
    ->send();
```

can automatically request enabled default optional channels.

Explicit:

``` php
->channels(['push', 'mqtt'])
```

overrides the optional requested-channel set for that notification.

Structural channels are not part of `channels()`.

## DeliveryPlan

Proposed value object:

``` php
final readonly class DeliveryPlan
{
    public function __construct(
        public object $recipient,
        public NotificationPayload $payload,
        public ?string $storedNotificationId,
        public array $channels,
        public string $correlationId,
    ) {}
}
```

`channels` contains `ChannelPlan` objects.

`payload.id` is the logical ID. `storedNotificationId` is allocated per recipient
when persistence is planned, otherwise null. It becomes the inbox primary key
only when execution persists the row. Snapshot recipient identity and normalized
values; the illustrative `object` type must not imply a live mutable model as
the source of plan decisions. See [ADR-0032](adr/0032-notification-identity.md).

## ChannelPlan

``` php
final readonly class ChannelPlan
{
    public function __construct(
        public string $channel,
        public ChannelPlanStatus $status,
        public array $destinations = [],
        public ?SkipReason $skipReason = null,
        public array $metadata = [],
    ) {}
}
```

## ChannelPlanStatus

``` php
enum ChannelPlanStatus: string
{
    case DELIVER = 'deliver';
    case SKIP = 'skip';
}
```

Execution outcomes do not belong in `ChannelPlanStatus`.

The plan describes intent before execution.

## SkipReason

Proposed v0.1 enum:

``` php
enum SkipReason: string
{
    case NOT_REQUESTED = 'not_requested';
    case DISABLED = 'disabled';
    case USER_PREFERENCE = 'user_preference';
    case NO_DESTINATION = 'no_destination';
    case PRESENCE = 'presence';
}
```

`presence` was added by accepted ADR-0036. The application-owned PresencePolicy
is evaluated after preferences and before destinations, only for optional channels.
Structural channels and contextual transports cannot be suppressed by presence.

`unsupported` is an exception when the application explicitly
references an unknown channel.

`unavailable` is not a planning status for enabled invalid
configuration.

Runtime outages are delivery failures.

## ChannelDelivery

Before calling the channel, executor creates:

``` php
final readonly class ChannelDelivery
{
    public function __construct(
        public object $recipient,
        public NotificationPayload $payload,
        public ?string $storedNotificationId,
        public RegisteredChannel $channel,
        public array $destinations,
        public string $correlationId,
        public array $metadata = [],
    ) {}
}
```

## DeliveryExecutor

Responsibilities:

``` text
receive DeliveryPlan
wait for after-commit boundary when applicable
persist inbox with allocated personal ID before dependent delivery
persist tracking when enabled (including planned/skipped records)
iterate DELIVER channel plans
create ChannelDelivery
dispatch synchronously or to queue
collect/emit DeliveryResult
emit lifecycle events
```

Conceptual contract:

``` php
interface DeliveryExecutor
{
    public function execute(
        DeliveryPlan $plan
    ): DeliveryExecutionResult;
}
```

The exact queue strategy can remain internal.

## Queue strategy

Default architectural behavior:

``` text
Notification dispatch request
        ↓
build plans
        ↓
wait for successful outermost commit when inside a transaction
        ↓
persist / enqueue delivery work
        ↓
emit execution lifecycle events
```

`send()` returns the immutable planning result immediately when execution is
deferred. Its `plannedQueueJobCount` counts planned asynchronous recipient/channel
jobs, not successful enqueue operations or individual destinations. Context
plans are counted separately. No tracking records are written by the planner.

External/slow channels should be queueable.

The package must not require Redis.

Reference initial queue:

``` text
database
```

Potential implementation choices:

### Option A --- one queued notification job per recipient

Pros: - simple; - recipient isolation.

Cons: - failure in one channel may retry successful channels unless
carefully tracked.

### Option B --- one queued delivery job per recipient/channel

Pros: - strongest isolation; - provider-specific retry; - easier
tracking.

Cons: - more jobs.

Recommended direction: **one delivery job per recipient/channel** for
optional/external channels, while persistence planning remains
coordinated centrally.

This should be validated with implementation prototypes before freezing.

## DeliveryResult

Provider-neutral result:

``` php
final readonly class DeliveryResult
{
    public function __construct(
        public string $channel,
        public DeliveryStatus $status,
        public ?string $provider = null,
        public ?string $providerReference = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $metadata = [],
    ) {}
}
```

## DeliveryStatus

``` php
enum DeliveryStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
}
```

A planned skip may optionally produce a skipped result when delivery
tracking is enabled.

## Idempotency

Delivery planning and execution should be designed to support
idempotency.

At minimum, every dispatch must have:

``` text
notification id
correlation id
recipient identity
channel name
```

A future idempotency key can be derived from:

``` text
notification_id + recipient + channel + destination
```

This is especially relevant for retries.

No exactly-once guarantee should be promised for external providers.

## Correlation ID

Every notification dispatch should carry a correlation identifier.

Purpose:

-   logs;
-   status tracing;
-   delivery tracking;
-   queue failures;
-   provider diagnostics.

It should be distinct from the notification's semantic ID when needed,
but reusing the notification UUID as the initial correlation identifier
is acceptable for v0.1.

## Planning example

Input:

``` text
type: incident.created

Global optional defaults:
push = requested
mail = not requested
mqtt = requested

Recipient Juan:
push preference = enabled
mqtt preference = enabled
push devices = 2
mqtt topics = 1
```

Plan:

``` text
database
status: deliver

broadcast
status: deliver

push
status: deliver
destinations:
- iphone token
- android token

mail
status: skip
reason: not_requested

mqtt
status: deliver
destinations:
- notifications/user/25
```

Recipient María:

``` text
database: deliver
broadcast: deliver
push: skip user_preference
mail: skip not_requested
mqtt: skip no_destination
```

## Context broadcast

`broadcastTo()` does not create recipient `ChannelPlan` entries.

It belongs to a separate contextual realtime plan.

Conceptually:

``` text
NotificationDispatchPlan
|
+-- recipient delivery plans
|   +-- Juan
|   +-- María
|   +-- Pedro
|
+-- context broadcasts
    +-- incident.347
```

This separation prevents user preferences from accidentally affecting
context synchronization.

## NotificationDispatchPlan

To represent the entire dispatch operation, introduce a higher-level
immutable value object:

``` php
final readonly class NotificationDispatchPlan
{
    public function __construct(
        public NotificationContext $context,
        public NotificationPayload $payload,
        public array $recipientPlans,
        public array $contextBroadcasts,
        public string $correlationId,
    ) {}
}
```

This becomes the natural handoff between orchestration and execution.

## Diagnostics

The planner should expose enough metadata to explain decisions.

Potential debugging API:

``` php
$plan = Notify::make(...)
    ->plan();
```

This may be considered for a later version or internal testing.

No public `plan()` method is frozen yet.

## Failure boundaries

Configuration failure:

``` text
throw before delivery
```

Planning skip:

``` text
normal condition, no exception
```

Runtime provider failure:

``` text
DeliveryResult::FAILED
queue retry where configured
```

Unknown explicitly requested channel:

``` text
exception
```

## Context delivery refinement

`NotificationDispatchPlan.contextBroadcasts` is generalized to
`contextDeliveries`.

Each entry is a `ContextDeliveryPlan`.

Initial context transports:

``` text
broadcast
mqtt
```

MQTT may therefore exist both as an optional recipient channel and as a
contextual transport.

Recipient MQTT plans remain preference/destination aware.

Context MQTT plans do not resolve recipients or apply recipient
preferences.

See `CONTEXT-DELIVERY.md`.
