# Domain Model

## Core concepts

### NotificationContext

Immutable description of a notification-producing occurrence.

Proposed fields:

``` php
NotificationContext(
    type,
    title,
    message,
    subject,
    actor,
    data,
    action,
    severity,
    occurredAt,
    correlationId
)
```

Not every field must be persisted directly.

### NotificationType

A stable semantic identifier, for example:

``` text
incident.created
incident.followup.created
property.map.authorization_required
property.technician.assigned
document.uploaded
```

The package must treat this as a string/value object and must not
require an application-specific enum.

### RecipientResolver

Application-owned strategy that determines recipients from a
`NotificationContext`.

``` php
interface RecipientResolver
{
    public function resolve(NotificationContext $context): iterable;
}
```

### ChannelPolicy

Determines the desired delivery channels.

Potential channel states:

``` text
required
allowed
disabled
```

### NotificationAction

Machine-readable action associated with the notification.

Example:

``` json
{
  "type": "route",
  "url": "/properties/678/incidents/347",
  "label": "View incident"
}
```

The package should not require Laravel named routes in the stored
payload because consumers may be web, mobile or external.

### Delivery

An optional record of a channel delivery attempt.

### Device

A registered notification endpoint belonging to a notifiable user.

### Preference

A user-specific preference for a notification type and channel.

## Standard payload

The same semantic payload should be reusable by database, broadcast and
push modules.

Proposed v1 payload:

``` json
{
  "schema": "1.0",
  "id": "notification-uuid",
  "type": "incident.created",
  "title": "New incident",
  "message": "A new incident was reported.",
  "severity": "info",
  "occurred_at": "2026-08-22T18:00:00Z",
  "actor": {
    "id": "123",
    "display": "User name"
  },
  "subject": {
    "type": "incident",
    "id": "347"
  },
  "data": {
    "property_id": 678
  },
  "action": {
    "type": "route",
    "url": "/properties/678/incidents/347"
  }
}
```

Sensitive application data must not be included unless explicitly
supplied by the consuming application.

## Identity

Notification IDs should use UUID/ULID-compatible string identifiers to
remain portable.

Distinguish the shared logical `notificationId` (`payload.id`) from each
recipient's `storedNotificationId` (inbox primary key and HTTP resource ID).
`correlationId` is operational correlation, and `deliveryId` identifies a logical
tracked delivery across retries. The inbox JSON payload retains the logical ID;
no dispatch table is required. See [ADR-0032](adr/0032-notification-identity.md).

The package should not assume integer user IDs. Notifiable identifiers
must be treated as scalar/stringable identifiers.

## Accepted v0.1 payload decisions

The canonical payload contract is defined in `PAYLOAD-SPECIFICATION.md`.

Key decisions:

``` text
severity      optional from developer; defaults to info
occurred_at   optional from developer; defaults to now()
data          always normalized to {}
actions       always normalized to []
```

Actions are plural from schema v1.0 and are intended to be directly
consumable by web and mobile clients without exposing Laravel-specific
controller or route internals.
