# ADR-0007 --- Versioned transport-neutral notification payload

Status: **Accepted**

[ADR-0032](0032-notification-identity.md) clarifies that semantic payload `id`
is logical; personal inbox resources have a separate recipient-specific ID.

## Decision

All delivery channels derive from a versioned semantic notification
payload.

Schema v1.0 includes:

``` text
schema
id
type
title
message
severity
occurred_at
actor
subject
data
actions
```

Defaults:

``` text
severity = info
occurred_at = now()
data = {}
actions = []
```

Actions are machine-readable and client-neutral. Laravel controller
names and named routes are not part of the transport contract.

Payload schema versioning is independent of Composer package versioning.
