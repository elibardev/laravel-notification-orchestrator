# ADR-0011 --- Fail-fast configuration and notifications:status

Status: **Accepted**

## Context

Notification delivery failures can be difficult to diagnose when enabled
providers are silently misconfigured.

## Decision

Enabled channels use fail-fast configuration validation.

Invalid configuration results in an explicit configuration exception
rather than silently marking the channel unavailable.

Operational provider outages are treated separately as runtime
delivery/health failures.

The package provides:

``` bash
php artisan notifications:status
```

as its standard infrastructure diagnostic command.

## Health model

``` text
HEALTHY
DEGRADED
INVALID
```

Registered third-party channels must be able to contribute their own
validation and health status through package contracts.

## Consequences

Positive:

-   configuration mistakes become immediately visible;
-   production notification failures are easier to diagnose;
-   third-party channels participate in the same diagnostics;
-   operational outages remain distinguishable from configuration
    defects.

Negative:

-   enabling a channel requires valid configuration before normal
    operation;
-   channel implementations must provide diagnostics contracts.
