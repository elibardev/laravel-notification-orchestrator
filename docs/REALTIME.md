# Realtime Architecture

## Scope

Realtime notification delivery uses Laravel Broadcasting. Reverb is the
recommended self-hosted broadcaster, but the package must not require
it.

Laravel's current broadcasting abstraction supports Reverb and other
broadcast backends, and broadcast notifications are queued by Laravel.

## Personal notification channel

The orchestrator should standardize a personal notification channel used
to:

-   deliver new notifications;
-   synchronize read state;
-   synchronize unread state;
-   synchronize "read all";
-   optionally synchronize preference/device changes.

The exact naming strategy must be configurable.

## Context channels

Channels such as:

``` text
property.678
incident.347
ticket.123
```

are application domain concerns.

The package may expose helper contracts but must not own
application-specific channel authorization.

## Presence

Presence is an optional capability.

It can be used to optimize delivery policies, for example:

``` text
Recipient is currently active in incident.347
    -> realtime message
    -> optionally suppress push

Recipient is not active there
    -> personal notification
    -> optional push
```

This behavior must be policy-driven, not hard-coded.

## Multiple clients

Read/unread state is persisted in the database.

Example:

``` text
Mobile marks notification N as read
        |
        v
Backend persists read_at
        |
        v
Broadcast NotificationRead(N)
        |
        +---- browser A updates
        +---- browser B updates
        +---- tablet updates
```

The server database remains authoritative.

## Reverb scaling

Single-instance Reverb does not require Redis merely to provide
WebSocket service.

If Reverb is horizontally scaled, Reverb uses Redis pub/sub to
coordinate messages across servers. This scaling requirement belongs to
deployment infrastructure rather than the package core.

## Personal synchronization protocol

The personal notification broadcast protocol includes:

``` text
notification.created
notification.read
notification.unread
notification.read_all
```

Realtime is not a replayable source of truth.

After reconnection clients should refresh authoritative unread count
and, where needed, the latest inbox page.

See `PERSISTENCE-AND-SYNC.md`.

## Context transports

Realtime contextual synchronization is transport-neutral.

Initial transports:

``` text
Laravel Broadcasting / Reverb
MQTT / Mosquitto
```

`broadcastTo()` is retained as a convenience API.

Generalized contextual routing uses `ContextTarget` /
`ContextDeliveryPlan`.

See `CONTEXT-DELIVERY.md`.
