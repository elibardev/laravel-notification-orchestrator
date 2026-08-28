# ADR-0005 — Application-owned recipient rules

Status: **Accepted**

## Context

Recipient rules depend on each application's domain: roles, assignments, ownership, geography, workflow state, tenancy and authorization.

## Decision

The package defines recipient-resolution contracts but does not define domain-specific recipient logic.

Applications provide resolvers.

## Consequences

The package remains reusable and does not accumulate business-specific concepts such as "auditor", "technician", "property" or "manager".
