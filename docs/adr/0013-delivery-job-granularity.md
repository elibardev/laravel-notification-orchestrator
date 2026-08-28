# ADR-0013 --- Delivery execution isolation

Status: **Accepted**

## Context

A single notification may target multiple recipients and external
channels. Provider failures should not unnecessarily retry successful
deliveries.

## Decision

Use one queued delivery job per recipient/channel for optional or
external channels.

A channel may resolve multiple destinations for that recipient. Delivery
tracking may record destination-level records when destinations are
independently deliverable.

This provides:

-   provider-specific retries;
-   recipient/channel failure isolation;
-   clearer delivery tracking;
-   simpler failed-job diagnosis;
-   prevention of unnecessary re-delivery through already successful
    channels.

Database queue remains a valid initial backend.

## Consequences

The number of jobs grows with:

``` text
recipients × selected queued channels
```

This is accepted in exchange for isolation and observability.

The executor and tracking model must preserve a stable logical delivery
identifier for retries.
