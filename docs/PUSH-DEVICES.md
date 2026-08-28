# Push Notifications and Devices

## Goal

Push is an optional delivery module.

The core orchestrator must work when push is disabled.

## Device lifecycle

A device record represents a push-capable endpoint associated with a notifiable entity.

Lifecycle:

```text
register
  |
  v
active token
  |
  +--> refreshed token
  |
  +--> disabled by user
  |
  +--> invalidated by provider
  |
  v
retired
```

## Device API

Potential application-facing operations:

These names are conceptual, not method aliases. Implemented repository methods
are register, update, disable, findFor, allFor and destinations; authenticated
HTTP paths are documented in [PHASE-3.md](PHASE-3.md).

```text
registerDevice()
updateDevice()
disableDevice()
removeDevice()
invalidateToken()
```

## FCM

FCM HTTP v1 is the implemented first push adapter. See [PHASE-3.md](PHASE-3.md)
for service-account setup, managed/external destinations and current contracts.

The package must isolate FCM-specific classes behind `PushDriver`.

## Push payload

Push payload should contain enough information to:

- identify the notification;
- render a concise title/body;
- route the application when tapped;
- mark the underlying persistent notification as read when appropriate.

Example of the FCM message projection (data values are strings):

```json
{
  "notification": {"title": "New record", "body": "A record was created."},
  "data": {
    "schema": "1.0",
    "id": "logical-uuid",
    "notification_id": "personal-inbox-uuid",
    "type": "record.created",
    "actions": "[]"
  }
}
```

## Read semantics

In the personal push projection above, `notification_id` is the recipient's
inbox ID, not the logical `payload.id`. Omit it when persistence is disabled;
that projection cannot mark a nonexistent inbox record read. See
[ADR-0032](adr/0032-notification-identity.md).

Provider acceptance or device delivery must never automatically mark the persistent notification as read.

Read state is changed by an authenticated application action.
