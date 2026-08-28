# ADR-0030 --- Provider-neutral observability through correlation, events and Laravel logging

Status: **Accepted**

Every notification dispatch carries notification/correlation
identifiers.

Use structured Laravel logs and lifecycle events.

Do not require a monitoring vendor.

Provider acceptance must not be described as end-user read/delivery
unless that level is actually confirmed.

Secrets/tokens are never logged.

An optional metrics abstraction may be added behind a null default.
