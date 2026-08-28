# ADR-0003 — Laravel abstractions over infrastructure providers

Status: **Accepted**

## Context

The project should support self-hosted Reverb and database queues initially while remaining compatible with other Laravel-supported backends.

## Decision

The package depends on Laravel's notification, queue and broadcasting abstractions rather than Reverb, Redis/Valkey or cloud providers directly.

Provider-specific functionality is implemented only in optional adapters.

## Consequences

An application can start with:

```text
Queue: database
Broadcast: Reverb
```

and later change infrastructure without rewriting recipient/business rules.
