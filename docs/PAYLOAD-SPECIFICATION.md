# Payload Specification

## Status

**Draft accepted as the v0.1 baseline.**

The payload is the transport-neutral representation of a notification.
Database, broadcast, web and mobile clients should derive their behavior
from the same semantic structure.

## Design goals

The payload must:

-   be easy to consume from web and mobile clients;
-   carry a human-readable title and message;
-   support machine-readable actions;
-   avoid Laravel-specific routing concepts;
-   remain versionable independently from the Composer package;
-   have predictable defaults;
-   distinguish notification semantics from per-user read state.

## Canonical payload

``` json
{
  "schema": "1.0",
  "id": "01K...",
  "type": "incident.created",
  "title": "Nueva incidencia",
  "message": "Se reportó una incidencia en la propiedad 678.",
  "severity": "info",
  "occurred_at": "2026-08-22T20:15:00Z",
  "actor": {
    "id": "25",
    "display": "Juan Pérez"
  },
  "subject": {
    "type": "incident",
    "id": "347"
  },
  "data": {
    "property_id": 678,
    "incident_id": 347
  },
  "actions": [
    {
      "id": "view_incident",
      "type": "navigate",
      "label": "Ver incidencia",
      "url": "/properties/678/incidents/347",
      "data": {
        "property_id": 678,
        "incident_id": 347
      }
    }
  ]
}
```

## Identity and personal projections

The semantic payload `id` identifies one logical notification, shared across
all recipients and context deliveries. It is the same logical ID returned by
`NotificationDispatchResult.notificationId`.

When persistence is planned, each recipient receives a different
`storedNotificationId`. That ID becomes the inbox row primary key, the personal
HTTP resource `id` and the identifier used for read/unread operations. The
unchanged semantic payload is stored in the row's JSON `data`, so `data.id`
remains the logical ID.

Build personal resources and push projections explicitly; do not blindly merge
the payload and row or overwrite the semantic ID. Personal push uses
`notification_id` for the personal inbox ID when one exists. Without persistence,
omit that field and do not fabricate read state. Context payloads always retain
the logical ID.

See [ADR-0032](adr/0032-notification-identity.md).

## Field contract

  -------------------------------------------------------------------------
  Field                  Required from           Present in Default
                             developer   normalized payload
  --------------- -------------------- -------------------- ---------------
  `schema`                          No                  Yes `1.0`

  `id`                              No                  Yes generated

  `type`                           Yes                  Yes none

  `title`                          Yes                  Yes none

  `message`                        Yes                  Yes none

  `severity`                        No                  Yes `info`

  `occurred_at`                     No                  Yes `now()`

  `actor`                           No             Optional `null`

  `subject`                         No             Optional `null`

  `data`                            No                  Yes `{}`

  `actions`                         No                  Yes `[]`
  -------------------------------------------------------------------------

`severity` and `occurred_at` are optional for the developer but are
always normalized before transport.

## Severity

Initial closed set:

``` text
info
success
warning
error
```

Default:

``` text
info
```

Severity is a presentation/semantic signal and must not be overloaded as
delivery priority.

A separate priority concept may be introduced later if required.

## occurred_at

Represents when the underlying event occurred.

If omitted:

``` php
occurred_at = now();
```

It is intentionally distinct from persistence timestamps such as
`created_at`.

## Actor

Optional description of the actor responsible for the occurrence.

The payload must not assume an integer identifier.

## Subject

Optional semantic reference to the primary domain object.

Example:

``` json
{
  "type": "incident",
  "id": "347"
}
```

It is not an authorization token.

## Data

Always an object, default `{}`.

Applications may add domain-specific machine-readable values required by
clients.

Sensitive information should not be included unless intentionally
exposed.

## Actions

Always an array, default `[]`.

Multiple actions are supported from the initial schema so a notification
can expose operations such as:

``` text
View
Approve
Reject
Respond
```

### Action contract

``` json
{
  "id": "approve_map",
  "type": "command",
  "label": "Autorizar",
  "url": null,
  "data": {
    "property_id": 676
  }
}
```

Fields:

  Field       Required Default
  --------- ---------- ---------
  `id`             Yes none
  `type`           Yes none
  `label`          Yes none
  `url`             No `null`
  `data`            No `{}`

Initial action types:

``` text
navigate
command
```

`id` represents application intent and should remain usable by web and
mobile clients.

`url` is optional convenience metadata, primarily useful to web clients.
Clients must not be required to understand Laravel named routes,
controllers or methods.

Example mobile interpretation:

``` text
action.id = view_incident
action.data.incident_id = 347

-> open native Incident screen
```

Example web interpretation:

``` text
action.type = navigate
action.url = /properties/678/incidents/347

-> navigate browser/router
```

### Security

An action is a UI capability declaration, not an authorization grant.

For a command:

``` text
notification action
       |
       v
authenticated API request
       |
       v
Laravel authorization / policy / business rule
       |
       v
execute or reject
```

The server remains authoritative.

## Read state

Read/unread state is not part of the semantic payload.

It belongs to the recipient-specific persisted notification state.

An HTTP API resource explicitly projects semantic fields plus personal state.
Here `id` is the personal inbox ID, not the semantic payload's logical ID:

``` json
{
  "id": "inbox-ana-uuid",
  "type": "incident.created",
  "title": "Nueva incidencia",
  "message": "Se reportó una incidencia.",
  "severity": "info",
  "occurred_at": "2026-08-22T20:15:00Z",
  "data": {},
  "actions": [],
  "state": {
    "read": false,
    "read_at": null
  }
}
```

## Schema versioning

Payload schema versioning is independent from Composer package
versioning.

Example:

``` text
Composer package: 0.8.2
Payload schema:   1.0
```

This permits package evolution without unnecessarily breaking existing
web/mobile clients.
