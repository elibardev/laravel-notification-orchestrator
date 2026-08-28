# ADR-0019 --- Context delivery is distinct from durable notification delivery

Status: **Accepted**

## Decision

Introduce `ContextDeliveryPlan` and transport-neutral `ContextTarget`.

Context delivery is used for realtime synchronization of a domain
context such as an incident, property or case.

It is separate from recipient `DeliveryPlan`.

`broadcastTo()` remains a convenience API for Laravel Broadcasting
context targets.

The generalized API is `contextTo(ContextTarget ...)`.

Application database state remains authoritative; context transports are
synchronization mechanisms, not event-history storage.
