# ADR-0016 --- Authoritative unread counters in API and realtime events

Status: **Accepted**

## Context

Client-maintained increment/decrement counters can drift because
realtime events may be duplicated, missed during reconnection or changed
from another device.

## Decision

The server computes the authoritative unread count.

Read/unread API responses and relevant personal realtime events include:

``` text
meta.unread_count
```

Clients assign this value rather than relying solely on local
increments/decrements.

## Consequence

Frontend behavior remains consistent across tabs and devices and can
recover after reconnect by refetching server state.
