# ADR-0001 — Single modular Composer package

Status: **Accepted**

## Context

The project is intended to include database notifications, realtime, preferences, devices, push, delivery tracking, API and presence-related integrations while allowing applications to activate only the required capabilities.

## Decision

Distribute the project as one Composer package with internally isolated feature modules.

## Consequences

Positive:

- one installation;
- one version line;
- coherent configuration;
- consistent payload and API;
- easier adoption.

Negative:

- repository can become large;
- optional dependencies must be managed carefully;
- strict module boundaries are required.

The core must not directly depend on optional provider-specific infrastructure.
