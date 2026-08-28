# ADR-0006 --- Initial platform compatibility

Status: **Accepted**

## Decision

Initial minimum platform:

``` text
Laravel: ^12.0
PHP: >= 8.2
```

Testing will define the verified compatibility matrix for newer
PHP/Laravel combinations.

## Queue reference deployment

Initial reference queue:

``` text
database
```

The package remains queue-driver agnostic.

## Realtime reference deployment

Recommended self-hosted realtime implementation:

``` text
Laravel Reverb
```

The package integrates through Laravel Broadcasting rather than directly
coupling the core to Reverb.
