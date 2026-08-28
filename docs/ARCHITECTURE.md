# Architecture

## 1. Objective

The package provides a reusable orchestration layer for application
notifications while preserving Laravel's native infrastructure
abstractions.

The package answers:

-   What notification occurred?
-   Who should receive it?
-   Through which enabled channels?
-   Which channels are allowed or mandatory for the event?
-   Which channels does a recipient prefer?
-   Should delivery be queued?
-   What persistent state should be stored?
-   How should read/unread state synchronize across clients?
-   Which optional capabilities are enabled?

The consuming application answers:

-   What constitutes a business event?
-   Which users are eligible recipients?
-   Which application-specific authorization rules apply?
-   What application route/action should be associated with the
    notification?

## 2. Architectural style

The package will use a **modular monolith / hexagonal package
architecture**.

Internally, functionality is divided into modules, but distributed as a
single Composer package.

``` text
Application business logic
          |
          v
+--------------------------------------+
| Notification Orchestrator            |
|                                      |
|  +------------- Core --------------+ |
|  | Context                          | |
|  | Dispatcher                       | |
|  | Recipient resolution contracts  | |
|  | Policy engine                    | |
|  | Payload                          | |
|  +----------------+-----------------+ |
|                   |                   |
|     +-------------+--------------+    |
|     |             |              |    |
| Database       Broadcast        Push  |
| module         module           module |
|     |             |              |    |
| Laravel DB   Broadcasting      Driver  |
+-----+-------------+--------------+----+
      |             |              |
      v             v              v
 Relational DB   Reverb/etc.   FCM/APNs/etc.
```

## 3. Hard dependency boundary

The **core** may depend on Laravel contracts and Illuminate components,
but it must not contain provider-specific code for:

-   Laravel Reverb;
-   Redis / Valkey;
-   Firebase;
-   APNs;
-   Pusher;
-   Ably;
-   a specific SQL database;
-   a particular frontend framework.

Integration modules may use those technologies through Laravel
abstractions or optional adapters.

## 4. Modules

### 4.1 Core

Always enabled.

Responsibilities:

-   `NotificationContext`;
-   semantic notification type;
-   immutable payload;
-   `RecipientResolver` contract;
-   `ChannelPolicy` contract;
-   `NotificationDispatcher`;
-   deduplication;
-   exclusions;
-   notification factory;
-   lifecycle events;
-   package configuration;
-   capability registry.

### 4.2 Database

Default: enabled.

Responsibilities:

-   persistent notification storage;
-   read/unread state;
-   optional metadata normalization;
-   repository abstraction;
-   retention hooks.

Laravel's database notification model should be leveraged whenever
practical rather than replaced.

### 4.3 Queue

Default: enabled, but uses the application's configured Laravel queue
connection.

The package must not require Redis.

Initial supported deployment:

``` text
QUEUE_CONNECTION=database
```

Future deployment can change to Redis/Valkey/SQS without changing
business code.

### 4.4 Broadcast / Realtime

Default: optional.

Responsibilities:

-   deliver notification payloads using Laravel Broadcasting;
-   synchronize notification creation/read/unread state;
-   expose standard channel naming strategy;
-   permit client-side realtime notification updates.

Reverb is recommended for self-hosted Laravel installations but is not a
package dependency.

### 4.5 Preferences

Default: optional.

Responsibilities:

-   per-user / per-type / per-channel preferences;
-   defaults;
-   mandatory-channel overrides;
-   policy evaluation.

### 4.6 Devices

Default: optional.

Responsibilities:

-   device registration;
-   device token lifecycle;
-   platform metadata;
-   enable/disable;
-   token invalidation;
-   last-used metadata.

### 4.7 Push

Default: optional.

Responsibilities:

-   push adapter contract;
-   FCM adapter as first planned implementation;
-   notification-to-push payload transformation;
-   deep-link/action metadata;
-   invalid-token handling.

### 4.8 Delivery tracking

Default: optional.

Responsibilities:

-   record delivery attempts;
-   channel;
-   state;
-   external provider reference where available;
-   timestamps;
-   failure reason.

This is not synonymous with "read".

### 4.9 Presence

Default: optional and deliberately separated from notification
persistence.

Responsibilities:

-   presence-aware delivery decisions;
-   integration hooks for Laravel presence channels;
-   optional suppression of redundant push notifications when a
    recipient is actively viewing a context.

Presence state is ephemeral and is not the source of truth for
notification state.

### 4.10 API

Default: optional.

Potential endpoints:

``` text
GET    /notifications
GET    /notifications/unread
GET    /notifications/unread-count
PATCH  /notifications/{notification}/read
PATCH  /notifications/{notification}/unread
POST   /notifications/read-all

POST   /notification-devices
DELETE /notification-devices/{device}

GET    /notification-preferences
PUT    /notification-preferences
```

All routes must support configurable prefix, middleware and route names.

## 5. Execution pipeline

``` text
Business event
     |
     v
NotificationContext
     |
     v
RecipientResolver
     |
     v
Normalize / deduplicate / exclude
     |
     v
ChannelPolicy
     |
     +------------------------------+
     |                              |
system capabilities          user preferences
     |                              |
     +---------------+--------------+
                     |
                     v
              Delivery plan
                     |
          +----------+----------+
          |          |          |
          v          v          v
       Database   Broadcast    Push
          |          |          |
          +----------+----------+
                     |
                     v
              Lifecycle events
```

## 6. Channel decision model

The pipeline above separates planning from execution. Planning is synchronous
and side-effect-free with respect to inbox, tracking, queue and publications.
Execution waits for the outermost successful application database commit when
needed. Rollback discards its scoped work. `NotificationDispatchResult` is an
immutable plan snapshot; `plannedQueueJobCount` does not claim successful enqueue.

Semantic payload IDs identify a logical dispatch. Personal inbox row/resource
IDs identify one recipient's notification. Context delivery retains the logical
ID, while read/unread operations use the personal ID. See
[ADR-0032](adr/0032-notification-identity.md) and
[ADR-0034](adr/0034-planning-and-after-commit-execution.md).

Delivery of optional channels is determined by three layers:

``` text
1. System capability
   Is the channel enabled and configured?

2. Event policy
   Did the event request this channel?

3. Effective recipient preference
   Does the user's explicit or inherited preference allow it?
```

A user preference may suppress an optional channel requested by the
event.

User preferences do not add channels that the event did not request.

Database persistence is structural and is evaluated separately. If the
database feature is enabled, users cannot disable persistence.

## 7. Recipient resolution

Recipient membership is evaluated once during `send()` planning, before any
after-commit execution. Preferences and destinations are selected at the same
time. Later application changes and queue retries do not rerun recipient
resolution.

The package must not create permanent WebSocket subscriptions for every
entity relationship.

Examples of application-defined rules:

``` text
incident.created
    = property participants
    + assigned technician
    + auditors
    + quality-control users

map.authorization_required
    = users with the relevant management role

technician.assigned
    = new technician
    + supervisor
```

Resolvers return notifiable objects. The dispatcher deduplicates them.

## 8. Realtime channel model

Three conceptual channel classes are recommended:

``` text
Personal channel
    Notification delivery and synchronization.

Context channel
    Live updates for an entity currently being viewed.

Presence channel
    Ephemeral knowledge of users currently present in a context.
```

The orchestrator's notification module primarily owns the **personal
notification channel**. Application-specific context channels remain
application concerns.

## 9. Read state

`read` is a persistent user-level state, not a per-device state.

``` text
Push delivered != read
WebSocket delivered != read
Toast displayed != read
```

A notification becomes read only when application semantics mark it as
read, typically when the user opens it or explicitly marks it read.

After marking it read, the package may broadcast a `NotificationRead`
synchronization event to the user's other active clients.

## 10. Extensibility

Extension points:

-   recipient resolvers;
-   channel policies;
-   notification payload transformers;
-   push drivers;
-   route/action builders;
-   repositories;
-   preference providers;
-   delivery tracking listeners;
-   event listeners.

No custom application logic should require modification of package
source code.

## 11. Service provider

The package exposes one primary Laravel service provider responsible
for:

-   merging configuration;
-   binding contracts;
-   registering commands;
-   loading migrations when enabled;
-   loading routes when enabled;
-   publishing assets/config;
-   registering capability modules.

Feature modules are registered conditionally.

Their sole activation source is `notification-orchestrator.features.*` in
`config/notification-orchestrator.php`. Module sections configure behavior;
duplicate module activation switches are invalid. Do not silently enable
dependencies. See [ADR-0033](adr/0033-canonical-feature-configuration.md).

## 12. Non-goals for v1

-   generic chat platform;
-   replacement for Laravel Broadcasting;
-   replacement for Firebase/APNs;
-   replacement for Laravel Queue;
-   generic message broker;
-   cross-application central notification microservice;
-   guaranteed exactly-once delivery across external providers;
-   application-specific authorization logic.

These can be integrated later without changing the core abstraction.

## Channel taxonomy

The orchestrator distinguishes structural channels, optional
recipient-delivery channels and contextual broadcasting.

``` text
Notification Orchestrator
|
+-- Structural
|   +-- database
|   +-- broadcast
|
+-- Optional recipient delivery
|   +-- push
|   +-- mail
|   +-- mqtt
|   +-- future channels
|
+-- Context realtime
    +-- broadcastTo(...)
        +-- Laravel Broadcasting
            +-- Reverb / configured Laravel broadcaster
```

### MQTT

MQTT is modeled as an optional notification channel.

Its purpose is publish/subscribe delivery to MQTT-capable recipients,
agents, desktop clients, services or other consumers.

The reference broker is Eclipse Mosquitto.

MQTT is not modeled as a mobile push provider and is not an
implementation of `broadcastTo()`.

### Context broadcast

`broadcastTo()` is an explicit contextual realtime target owned by the
consuming application.

Example:

``` php
Notify::make('incident.followup.created')
    ->title('Nuevo seguimiento')
    ->message('Se agregó un seguimiento.')
    ->recipients(IncidentRecipients::class)
    ->broadcastTo("incident.{$incident->id}")
    ->send();
```

`recipients()` determines who receives the personal/persistent
notification.

`broadcastTo()` determines which active application context should
receive the same occurrence in realtime.

If no persistent/personal notification is required, the consuming
application should use Laravel Broadcasting directly rather than the
orchestrator.

## Operational diagnostics

Enabled notification channels follow a fail-fast configuration policy.

The package provides:

``` bash
php artisan notifications:status
```

as the standard diagnostic command.

Channel-specific diagnostics are supplied through `ChannelRegistry`
contracts rather than hard-coded into the console command.

See:

-   `CHANNELS-AND-DELIVERY.md`
-   `STATUS-COMMAND.md`

## Delivery tracking

Delivery tracking is optional and records transport lifecycle per
logical delivery.

It is intentionally separate from:

``` text
notification persistence/read state
Laravel failed_jobs
provider-specific logs
```

Accepted queue isolation is one job per recipient/channel for
optional/external channels.

See `DELIVERY-TRACKING.md`.

## Notification persistence and synchronization

Persistent database notification state is authoritative.

The personal broadcast channel synchronizes connected clients using
versioned events:

``` text
notification.created
notification.read
notification.unread
notification.read_all
```

Relevant events include an authoritative server-computed unread count.

Read state is global per recipient, not per device.

See `PERSISTENCE-AND-SYNC.md`.

## Devices, Push and Context Delivery

Push endpoint storage is optional and independent of application
authentication/session storage.

MQTT and Laravel Broadcasting may operate in both recipient-oriented and
context-oriented roles.

Context delivery is represented separately from recipient delivery plans
and is used for realtime domain synchronization.

See:

-   `DEVICES-AND-PUSH.md`
-   `CONTEXT-DELIVERY.md`

## Installation and frontend adapters

Package features are registered conditionally through the Service
Provider and CapabilityRegistry.

The package includes optional first-class Blade components backed by the
same HTTP/realtime protocol used by external clients.

See:

-   `INSTALLATION-AND-LIFECYCLE.md`
-   `BLADE-AND-FRONTEND.md`

## Architecture hardening

The pre-implementation architecture includes explicit policies for:

``` text
security/authentication boundaries
retention/pruning
observability/logging
versioning/backward compatibility
```

See the dedicated specifications in `docs/`.
