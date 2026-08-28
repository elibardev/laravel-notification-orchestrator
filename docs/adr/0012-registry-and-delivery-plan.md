# ADR-0012 --- Registry-driven channels and immutable delivery plans

Status: **Accepted**

## Context

The orchestrator must support built-in and third-party notification
channels without adding channel-specific branching to core logic.

Delivery decisions also need to be inspectable, testable and separated
from actual provider execution.

## Decision

Use a registry-driven channel architecture.

Each channel is represented by a registered immutable
`ChannelDefinition` plus an implementation resolved through the Laravel
container.

For each recipient, the orchestrator builds an immutable `DeliveryPlan`
before executing deliveries.

Contextual `broadcastTo()` targets are represented separately from
recipient delivery channels.

## Consequences

Positive:

-   third-party channel extensibility;
-   deterministic planning;
-   testable preference/destination logic;
-   clear failure boundaries;
-   diagnostics can explain skipped channels;
-   execution can be isolated by recipient/channel.

Negative:

-   more internal value objects and contracts;
-   planner/executor separation adds initial implementation complexity.
