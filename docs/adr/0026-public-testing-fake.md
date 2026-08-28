# ADR-0026 --- Public orchestrator testing fake

Status: **Accepted**

## Decision

Provide a Laravel-native public testing fake:

``` php
Notify::fake();
```

The fake executes validation, recipient resolution, preferences and
planning but suppresses actual provider delivery and default
persistence.

It records normalized notification contexts and dispatch plans.

The initial assertion surface includes logical send, recipient,
channel-plan and context-delivery assertions.
