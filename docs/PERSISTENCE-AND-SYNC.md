# Notification Persistence, Read State and Multi-Device Synchronization

## Status

Draft v0.1 --- implementation-oriented design.

## Purpose

This module defines how persisted notifications live after dispatch and
how their user-specific state remains synchronized across:

``` text
web browser
multiple browser tabs
mobile app
tablet
other authenticated clients
```

The database is the authoritative source of truth.

Realtime transport accelerates synchronization but does not replace
persistence.

## Core principles

1.  Notification persistence is structural when the database feature is
    enabled.
2.  Read state belongs to the recipient, not to a device.
3.  Push delivery, broadcast delivery and UI rendering do not imply
    read.
4.  All clients converge on server state.
5.  Realtime events carry authoritative counters/state whenever
    practical.
6.  Lost realtime events must be recoverable through normal HTTP/API
    synchronization.
7.  Operations must be idempotent.

## Persistent notification model

The initial design should remain compatible with Laravel database
notifications where practical.

Logical table:

``` text
{prefix}notifications
```

Proposed schema:

``` text
id
type

notifiable_type
notifiable_id

data

read_at nullable

created_at
updated_at
```

The normalized orchestrator payload is stored inside `data`.

Example:

``` json
{
  "schema": "1.0",
  "id": "logical-notification-uuid",
  "type": "incident.created",
  "title": "Nueva incidencia",
  "message": "Se reportó una incidencia.",
  "severity": "info",
  "occurred_at": "2026-08-22T20:15:00Z",
  "actor": null,
  "subject": {
    "type": "incident",
    "id": "347"
  },
  "data": {
    "property_id": 678
  },
  "actions": []
}
```

The database notification row's `id` is the recipient-specific
`storedNotificationId` exposed by the inbox API. JSON `data.id` retains the
logical notification ID shared across recipients. These are distinct identities;
see [ADR-0032](adr/0032-notification-identity.md).

Allocate personal IDs during planning when persistence is enabled, but do not
insert rows until execution after commit. Retries reuse those IDs. A logical
notification `N1` may therefore produce inbox row `A1` for Ana and `L1` for Luis,
with independent `read_at` state.

## Read state

Initial persistent state:

``` text
unread
read
```

Representation:

``` text
read_at = NULL
    -> unread

read_at != NULL
    -> read
```

No separate boolean is required.

## Read semantics

A notification should be marked read only through an explicit
authenticated application action.

Typical triggers:

``` text
user opens notification target
user clicks/taps notification
user explicitly chooses Mark as read
client marks notifications read as part of an approved UX workflow
```

Not valid triggers:

``` text
push provider accepted message
push appeared on lock screen
WebSocket event arrived
toast was rendered
email was sent
MQTT message was published
```

## Repository contract

Proposed:

``` php
interface NotificationRepository
{
    public function paginateFor(
        object $notifiable,
        NotificationQuery $query
    ): NotificationPage;

    public function findFor(
        object $notifiable,
        string $storedNotificationId
    ): ?StoredNotification;

    public function unreadCount(
        object $notifiable
    ): int;

    public function markRead(
        object $notifiable,
        string $storedNotificationId,
        ?DateTimeInterface $at = null
    ): NotificationStateChange;

    public function markUnread(
        object $notifiable,
        string $storedNotificationId
    ): NotificationStateChange;

    public function markAllRead(
        object $notifiable,
        ?DateTimeInterface $at = null
    ): BulkNotificationStateChange;
}
```

Exact public signatures remain provisional during `0.x`.

## Idempotency

### markRead

Calling `markRead()` on an already-read notification:

``` text
must not fail
must not change read_at unnecessarily
must return current authoritative state
```

### markUnread

Calling `markUnread()` on an already-unread notification is also
idempotent.

### markAllRead

Repeated calls produce a valid result even when unread count is already
zero.

## Ownership and security

Every read/unread operation must be scoped to the authenticated
notifiable.

Never perform:

``` text
find notification globally by ID
then update without ownership scope
```

Required conceptual query:

``` text
WHERE id = stored_notification_id
AND notifiable_type = current type
AND notifiable_id = current id
```

Unknown or foreign notifications should appear unavailable to the caller
according to application/API policy.

## NotificationQuery

Initial query features:

``` text
all
unread only
read only
notification type filter
created-before cursor
page/cursor limit
```

Do not overload v0.1 with domain-specific filters.

## Pagination

Cursor pagination is recommended for notification feeds because
notifications are append-oriented and can grow continuously.

Stable ordering:

``` text
created_at DESC
id DESC
```

HTTP API may still expose a conventional paginator abstraction, but
cursor semantics are preferred internally.

## API representation

Recommended notification resource:

This is a personal projection: its `id` is the inbox primary key, not the
semantic payload's logical ID. Every `notification_id` in the read/unread
responses, personal state events and personal push example below also refers
to the personal inbox ID. Build these projections explicitly; never mutate the
underlying semantic payload to make its ID personal.

``` json
{
  "id": "01K...",
  "type": "incident.created",
  "title": "Nueva incidencia",
  "message": "Se reportó una incidencia.",
  "severity": "info",
  "occurred_at": "2026-08-22T20:15:00Z",
  "actor": null,
  "subject": {
    "type": "incident",
    "id": "347"
  },
  "data": {
    "property_id": 678
  },
  "actions": [],
  "state": {
    "read": false,
    "read_at": null
  },
  "created_at": "2026-08-22T20:15:01Z"
}
```

`created_at` is persistence metadata and is distinct from semantic
`occurred_at`.

## HTTP API

Initial proposed endpoints:

``` text
GET   /notifications
GET   /notifications/unread-count

PATCH /notifications/{notification}/read
PATCH /notifications/{notification}/unread

POST  /notifications/read-all
```

Optional convenience:

``` text
GET /notifications?state=unread
GET /notifications?type=incident.created
```

Exact route prefix remains configurable.

## Response for read

Example:

``` json
{
  "notification_id": "01K...",
  "state": {
    "read": true,
    "read_at": "2026-08-22T21:15:37Z"
  },
  "meta": {
    "unread_count": 6
  }
}
```

## Response for unread

``` json
{
  "notification_id": "01K...",
  "state": {
    "read": false,
    "read_at": null
  },
  "meta": {
    "unread_count": 7
  }
}
```

## Response for read-all

``` json
{
  "changed": 7,
  "meta": {
    "unread_count": 0
  }
}
```

The server-provided unread count is authoritative.

## Why authoritative counts

Clients should not rely only on:

``` text
counter++
counter--
```

because events can be:

``` text
missed during reconnection
received twice
processed out of order
changed on another device
```

Preferred:

``` text
notification event
includes unread_count
        ↓
client assigns exact value
```

Example:

``` javascript
store.unreadCount = event.meta.unread_count;
```

rather than:

``` javascript
store.unreadCount++;
```

## Personal realtime channel

When structural `broadcast` is enabled, each authenticated notifiable
listens to a personal notification channel.

The exact naming strategy may follow Laravel's notification broadcast
convention or a configurable package abstraction.

The package must not require the application to manually subscribe users
to every domain/context channel.

Personal channel purposes:

``` text
new notification
read state synchronization
unread state synchronization
read-all synchronization
authoritative unread count
```

## Realtime event envelope

Realtime synchronization events are separate from the semantic
notification payload.

Proposed envelope:

``` json
{
  "schema": "1.0",
  "event": "notification.created",
  "occurred_at": "2026-08-22T20:15:02Z",
  "notification": {},
  "meta": {
    "unread_count": 7
  }
}
```

## Initial realtime event names

``` text
notification.created
notification.read
notification.unread
notification.read_all
```

Potential future events:

``` text
notification.deleted
notification.archived
notification.preference_changed
```

Not part of v0.1 unless required.

## notification.created

Sent after persistent notification creation.

Example:

``` json
{
  "schema": "1.0",
  "event": "notification.created",
  "notification": {
    "id": "01K...",
    "type": "incident.created",
    "title": "Nueva incidencia",
    "message": "Se reportó una incidencia.",
    "severity": "info",
    "occurred_at": "...",
    "data": {},
    "actions": [],
    "state": {
      "read": false,
      "read_at": null
    }
  },
  "meta": {
    "unread_count": 7
  }
}
```

## notification.read

``` json
{
  "schema": "1.0",
  "event": "notification.read",
  "notification_id": "01K...",
  "state": {
    "read": true,
    "read_at": "2026-08-22T21:15:37Z"
  },
  "meta": {
    "unread_count": 6
  }
}
```

## notification.unread

Same shape with:

``` json
{
  "state": {
    "read": false,
    "read_at": null
  }
}
```

## notification.read_all

``` json
{
  "schema": "1.0",
  "event": "notification.read_all",
  "state": {
    "read_at": "2026-08-22T21:20:00Z"
  },
  "meta": {
    "unread_count": 0
  }
}
```

A client may refetch the current page after `read_all` if it needs exact
per-item state for notifications not currently loaded.

## Multi-device scenario

User has:

``` text
iPhone
office browser
laptop browser
```

All share one persistent inbox.

Sequence:

``` text
notification created
      ↓
database unread
      ↓
broadcast notification.created
      ↓
all connected clients receive unread_count = 5
```

User taps notification on iPhone:

``` text
iPhone
  ↓
PATCH /notifications/{id}/read
  ↓
database read_at updated
  ↓
server computes unread_count = 4
  ↓
broadcast notification.read
  ↓
office browser -> 4
laptop browser -> 4
iPhone -> 4
```

The originating client may receive its own synchronization event. Client
handlers must therefore be idempotent.

## Mobile push interaction

When persistence is enabled, the personal push projection includes the
recipient's persistent inbox ID. Without persistence, omit `notification_id`
and do not attempt mark-read. The semantic payload retains its logical ID.

Example:

``` json
{
  "notification_id": "01K...",
  "type": "incident.created",
  "action": {
    "id": "view_incident",
    "data": {
      "incident_id": 347
    }
  }
}
```

When user taps:

``` text
open app/deep link
       +
mark notification read through authenticated backend
```

Ordering may be:

``` text
1. navigate
2. mark read
```

or:

``` text
1. mark read
2. navigate
```

depending on client architecture.

Failure to mark read must not prevent authorization of the target
resource.

## Browser startup synchronization

On login/application boot, the frontend should not assume WebSocket
history.

Recommended bootstrap:

``` text
GET /notifications/unread-count
GET /notifications?limit=N
then subscribe personal realtime channel
```

To minimize race windows, frontend SDK may eventually implement:

``` text
subscribe
then refresh authoritative state
```

or use a bootstrap endpoint returning both notifications and count.

## Proposed bootstrap endpoint

Optional convenience API:

``` text
GET /notifications/bootstrap
```

Potential response:

``` json
{
  "notifications": [],
  "meta": {
    "unread_count": 7
  }
}
```

This is proposed, not yet mandatory.

## Reconnection strategy

After Echo/Reverb reconnects:

``` text
do not assume all events were received
```

Client should refresh authoritative state:

``` text
unread count
and optionally latest notification page
```

A future JS client can automate this.

## Frontend store contract

The project may later publish a JS/TS client.

Conceptual store:

``` text
NotificationStore
- items
- unreadCount
- upsert(notification)
- markRead(id, state)
- markUnread(id)
- markAllRead()
- replaceUnreadCount(value)
- synchronize()
```

Incoming notifications must be deduplicated by notification ID.

## Ordering and race conditions

Clients may receive events out of order.

Each state-changing event should carry:

``` text
server timestamp
authoritative unread_count
```

For per-notification state, `read_at` can be used to avoid applying an
older read event over newer state where relevant.

If uncertainty exists, client should refetch server state.

## Concurrent operations

Two devices may mark the same notification read simultaneously.

Expected behavior:

``` text
first update changes row
second update is idempotent
both responses represent read=true
both report authoritative unread count
```

No conflict should be surfaced to the user.

## Transaction boundary

Persistent notification creation should occur before realtime
`notification.created` is emitted.

Required execution sequence:

``` text
business transaction commits
      ↓
notification persisted
      ↓
broadcast queued/emitted
```

Avoid notifying clients about a database record that can later roll
back.

During `send()`, normalize and plan without persistence or delivery effects.
Use Laravel after-commit execution on the application's dispatch connection;
wait for the outermost successful commit. Rollback discards work in its scope.
Without a transaction, execute immediately after planning. These rules apply
even when queueing is synchronous or disabled.

## Notification creation and queueing

The structural database notification must be persisted before optional delivery
channels or personal events expose its allocated inbox ID to a client.

Recommended conceptual sequence:

``` text
send(): resolve recipients/preferences/destinations and allocate IDs
        ↓
freeze plans and result counts without writes
        ↓
wait for successful commit if inside a transaction
        ↓
persist inbox rows with allocated storedNotificationId values
        ↓
persist tracking when enabled; execute/enqueue prepared deliveries
        ↓
publish personal notification.created / context work per plan
```

Implementation may optimize this while preserving the invariant that
clients never receive an unknown notification ID.

Here "notification ID" means the personal ID exposed for inbox operations.
No inbox is invented when database persistence is disabled. Context payloads
use logical IDs and do not imply inbox existence.

Do not rebuild plans or rerun resolvers after commit/retries. Later application
changes do not alter the snapshot selected by `send()`. An after-commit failure
cannot roll back committed business data; crash recovery between commit and
enqueue is not guaranteed without a separately designed outbox.
See [ADR-0034](adr/0034-planning-and-after-commit-execution.md).

## Deletion

Hard deletion is intentionally not part of v0.1 behavior.

Reasons:

-   audit/history usefulness;
-   multi-device synchronization complexity;
-   accidental disappearance.

Retention/pruning policy can be designed separately.

## Archiving

Archive state is also deferred.

Initial inbox state remains:

``` text
read/unread
```

Keep v0.1 narrow.

## Read-all scope

Initial `read-all` means:

``` text
all unread notifications for current notifiable
```

Not:

``` text
current page only
current notification type only
```

Filtered bulk read may be considered later.

## Database indexing

Recommended indexes:

``` text
(notifiable_type, notifiable_id, read_at)
(notifiable_type, notifiable_id, created_at)
(notifiable_type, notifiable_id, type)
```

Exact index shape should be tested against target databases.

## Retention

Notification inbox retention is separate from delivery tracking
retention.

No automatic inbox pruning should be enabled by default in v0.1.

Applications may later configure notification retention policies.

## Observability

Potential lifecycle events:

``` text
StoredNotificationCreated
NotificationMarkedRead
NotificationMarkedUnread
NotificationsMarkedAllRead
```

These are distinct from delivery-tracking events.

## Failure behavior

Persistence failure:

``` text
structural failure
-> notification dispatch should fail
```

Personal broadcast failure after successful persistence:

``` text
notification remains safely stored
-> connected UI may not update immediately
-> clients recover through HTTP/bootstrap/reconnect sync
```

This is an important resilience property.

## Non-goals for v0.1

-   archive folders;
-   delete/trash workflow;
-   notification grouping/threading;
-   snooze;
-   per-device read state;
-   read receipts from lock-screen visibility;
-   guaranteed realtime event replay;
-   cross-application unified inbox.
