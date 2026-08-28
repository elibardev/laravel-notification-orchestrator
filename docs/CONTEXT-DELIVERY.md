# Context Delivery Specification

## Status

Draft v0.1 --- implementation-oriented design.

## Purpose

Context Delivery distributes realtime domain events to subscribers of a
shared context rather than to individually resolved notification
recipients.

Examples:

``` text
incident.347
property.678
case.55
project.12
```

This is distinct from recipient notification delivery.

## Recipient vs context delivery

Recipient delivery answers:

> Which people must receive a notification?

Context delivery answers:

> Which clients are currently subscribed to this domain context and
> should receive its realtime update?

Example:

``` text
Incident 347

Recipient delivery:
- assigned technician
- supervisor
- auditor

Context delivery:
- every authorized client currently subscribed to incident 347
```

A client may receive both. Client-side deduplication must use
event/notification identifiers where applicable.

Context payload `id` is the shared logical notification ID. Personal inbox
resources have a distinct ID per recipient, so clients must not compare those
IDs as though they were interchangeable. Context plans contain no personal
inbox ID or read state. See [ADR-0032](adr/0032-notification-identity.md).

## ContextDeliveryTransport

Context delivery is transport-agnostic.

Initial transports:

``` text
broadcast
mqtt
```

Reference providers:

``` text
broadcast -> Laravel Broadcasting / Reverb
mqtt      -> MQTT broker / Eclipse Mosquitto
```

## Important MQTT classification

MQTT is not exclusively a recipient notification channel.

The same MQTT transport can participate in two roles:

``` text
Recipient Delivery
└── MQTT personal destination
    └── notifications/user/25

Context Delivery
└── MQTT contextual destination
    └── incidents/347
```

The two roles use different planning semantics.

## Broadcast symmetry

Laravel Broadcasting has the same conceptual split:

``` text
Recipient Delivery
└── personal notification channel

Context Delivery
└── incident.347
```

Therefore the architecture is symmetrical:

``` text
                    Transport
                  /           \
          Recipient            Context
          delivery             delivery
             |                    |
      broadcast / mqtt      broadcast / mqtt
```

## ContextTarget

Proposed value object:

``` php
final readonly class ContextTarget
{
    public function __construct(
        public string $transport,
        public string $destination,
        public array $options = [],
    ) {}
}
```

Examples:

``` php
ContextTarget::broadcast(
    "incident.{$incident->id}"
);

ContextTarget::mqtt(
    "incidents/{$incident->id}",
    qos: 1,
    retain: false,
);
```

Exact static constructors remain provisional.

## Public builder

Existing convenience API remains valid:

``` php
->broadcastTo("incident.{$incident->id}")
```

For transport-neutral extensibility, introduce:

``` php
->contextTo(
    ContextTarget::mqtt(
        "incidents/{$incident->id}",
        qos: 1
    )
)
```

`broadcastTo()` may internally be syntactic sugar for a broadcast
`ContextTarget`.

Avoid proliferating provider-specific builder methods such as:

``` text
mqttTo()
redisTo()
teamsTo()
...
```

unless later ergonomics justify them.

## ContextDeliveryPlan

``` php
final readonly class ContextDeliveryPlan
{
    public function __construct(
        public string $transport,
        public string $destination,
        public NotificationPayload $payload,
        public array $options,
        public string $correlationId,
    ) {}
}
```

Context plans do not contain recipient preferences.

Build them during `send()` alongside recipient plans, without publishing or
writing tracking. Execute only after the outermost successful commit if a
transaction is active. `contextDeliveryCount` counts these plans, not subscribers
or successful publishes. Context work is excluded from `plannedQueueJobCount`.
See [ADR-0034](adr/0034-planning-and-after-commit-execution.md).

## NotificationDispatchPlan

Updated shape:

``` text
NotificationDispatchPlan
│
├── NotificationContext
├── NotificationPayload
├── correlationId
│
├── RecipientDeliveryPlans
│   ├── recipient A
│   │   ├── database
│   │   ├── broadcast personal
│   │   ├── push
│   │   ├── mail
│   │   └── mqtt personal (optional)
│   └── recipient B ...
│
└── ContextDeliveryPlans
    ├── broadcast → incident.347
    └── mqtt      → incidents/347
```

## Context transport registry

Context transports should use a registry pattern analogous to recipient
channels.

Proposed:

``` php
interface ContextTransportRegistry
{
    public function register(
        string $name,
        string|ContextDeliveryTransport $implementation
    ): void;

    public function get(string $name): RegisteredContextTransport;

    public function has(string $name): bool;

    public function validateEnabled(): void;
}
```

## ContextDeliveryTransport

``` php
interface ContextDeliveryTransport
{
    public function name(): string;

    public function validateConfiguration(): void;

    public function health(): ChannelHealth;

    public function publish(
        ContextDelivery $delivery
    ): DeliveryResult;
}
```

The common `ChannelHealth`/result primitives may be shared with
recipient channels where semantics align.

## MQTT contextual delivery

Example:

``` text
event:
incident.followup.created

context:
incident 347

topic:
incidents/347
```

One MQTT publish is made:

``` text
PUBLISH incidents/347
```

Mosquitto fans out to all authorized connected subscribers.

Laravel does not enumerate active subscribers.

## MQTT topic strategy

Do not expose arbitrary application topics to untrusted clients without
authorization design.

Recommended topic namespaces:

``` text
notifications/users/{opaque-id}
incidents/{opaque-id}
properties/{opaque-id}
```

Applications may customize topic naming.

Avoid embedding PHP class namespaces or secrets.

## MQTT QoS

Initial support should expose:

``` text
QoS 0
QoS 1
```

Default recommendation for notification/context events:

``` text
QoS 1
```

This gives at-least-once delivery and requires client deduplication.

QoS 2 should not be required for v0.1.

## MQTT retain

Default:

``` text
retain = false
```

A realtime incident follow-up is an event, not current-state storage.

Retained MQTT messages could cause a newly subscribing client to
interpret an old event as new.

If current state is required, clients should fetch it from the
application API/database.

## MQTT authorization

Mosquitto must not trust topic names alone.

The deployment must authenticate clients and authorize subscribe/publish
access.

The Laravel application/server publishes.

End clients normally subscribe only to authorized topics.

Exact broker ACL integration is deployment-specific and should be
documented separately from core orchestration.

## Context delivery tracking

Context delivery has different semantics from recipient tracking.

For:

``` text
mqtt -> incidents/347
```

the orchestrator can track:

``` text
planned
queued
processing
sent
failed
```

but cannot claim:

``` text
delivered to user A
delivered to user B
```

unless application-level acknowledgements are implemented.

Likewise a successful Reverb broadcast means the transport
accepted/emitted the event, not that every subscriber rendered it.

Therefore contextual tracking should be transport-level, not
recipient-level.

A future optional table may be:

``` text
{prefix}context_deliveries
```

This is not required for initial v0.1 unless operational requirements
demand it.

## Context payload

Use the same normalized semantic payload where appropriate.

Example:

``` json
{
  "schema": "1.0",
  "id": "logical-notification-uuid",
  "type": "incident.followup.created",
  "title": "Nuevo seguimiento",
  "message": "Se agregó un seguimiento.",
  "severity": "info",
  "occurred_at": "2026-08-27T12:00:00Z",
  "subject": {
    "type": "incident",
    "id": "347"
  },
  "data": {
    "followup_id": 851
  },
  "actions": []
}
```

Clients should use `type`, IDs and actions rather than parsing
human-readable `message`.

## Database authority

Context transports are not authoritative storage.

For chat-like incident followups:

``` text
POST followup
    ↓
persist followup in application DB
    ↓
transaction commits
    ↓
publish context event
```

If realtime delivery is missed, the client reloads followups from the
API.

## Client behavior

For incident 347:

``` text
open incident screen
    ↓
authorize access
    ↓
subscribe context transport
    ↓
fetch current followups
    ↓
apply incoming events
```

On reconnect:

``` text
resubscribe
+
refresh authoritative incident state
```

No assumption of complete realtime event replay.

## Relationship to notifications

A single domain event may produce both:

``` text
persistent personal notifications
+
context realtime updates
```

Example:

``` text
incident.followup.created

Recipient notification:
"Juan added a follow-up to incident 347"

Context update:
append followup 851 to currently open incident view
```

The notification is durable user attention state.

The context event is realtime UI synchronization.

They should not be conflated.
