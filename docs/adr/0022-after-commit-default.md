# ADR-0022 --- Automatic after-commit notification dispatch

Status: **Accepted**

Clarified by [ADR-0034](0034-planning-and-after-commit-execution.md): planning
and recipient resolution happen during `send()`; persistence, queueing and
delivery effects remain deferred until successful commit.

## Decision

When notification dispatch occurs inside an active database transaction,
defer notification persistence/dispatch until after successful commit
where Laravel infrastructure supports it.

Application developers do not need to explicitly request after-commit
behavior in normal usage.

No public before-commit override is included in v0.1.

## Rationale

Prevents durable/realtime/external notifications from being emitted for
business state that later rolls back.
