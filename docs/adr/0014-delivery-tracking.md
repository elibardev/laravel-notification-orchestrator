# ADR-0014 --- Optional destination-aware delivery tracking

Status: **Accepted**

[ADR-0032](0032-notification-identity.md) clarifies that tracking uses the logical
notification ID, not an inbox foreign key. [ADR-0034](0034-planning-and-after-commit-execution.md)
defers tracking writes until the execution boundary after commit when applicable.

## Context

The orchestrator needs operational traceability for queued/external
channel delivery without confusing transport state with notification
read state.

## Decision

Introduce an optional Delivery Tracking module.

Tracking identity is based on:

``` text
notification
+ recipient
+ channel
+ destination fingerprint
```

when destination-level tracking is applicable.

Canonical lifecycle:

``` text
planned
queued
processing
sent
delivered
failed
skipped
```

`delivered` is only used when the provider can reliably confirm
delivery.

Read/unread remains a separate persistent notification state.

## Privacy

Raw destination identifiers are not stored by default. The module stores
a destination hash and optional human-readable label.

## Retry model

One logical delivery row survives multiple queue retries.

Laravel Queue remains responsible for retry scheduling and
`failed_jobs`.

## Default tracking scope

When tracking is enabled:

``` text
optional external channels -> tracked by default
database/broadcast structural channels -> not tracked by default
```
