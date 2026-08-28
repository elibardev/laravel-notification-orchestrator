# ADR-0024 --- First-class Blade integration

Status: **Accepted**

## Decision

The Composer package will include optional first-class Blade
integration.

Initial public components:

``` blade
<x-notifications::bell />
<x-notifications::inbox />
<x-notifications::toast-container />
```

The Blade UI consumes the same HTTP/realtime protocol as other clients.

No separate notification business logic is implemented in Blade
components.

The frontend client remains framework-neutral and must not require Vue
or React.
