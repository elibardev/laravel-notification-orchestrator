# ADR-0015 --- Database-authoritative read state and multi-device synchronization

Status: **Accepted**

[ADR-0032](0032-notification-identity.md) clarifies the distinct logical and
recipient inbox identities without changing recipient-owned read state.

## Context

Users may receive and interact with notifications across multiple
browsers and mobile devices.

A per-device read model would produce inconsistent notification counters
and duplicate unread states.

Realtime events may also be lost or duplicated.

## Decision

Use the application database as the authoritative source of notification
read/unread state.

Read state is recipient-level, not device-level.

Realtime broadcasting synchronizes connected clients but does not
replace persistence.

Every state-changing response/event should include an authoritative
unread count where practical.

## Structural behavior

``` text
database persistence
-> authoritative

personal broadcast
-> synchronization accelerator
```

If personal broadcast fails, persisted state remains correct and clients
recover through HTTP synchronization.

## Idempotency

`markRead`, `markUnread` and `markAllRead` are idempotent operations.
