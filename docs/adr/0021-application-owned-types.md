# ADR-0021 --- Application-owned notification types

Status: **Accepted**

## Decision

Notification types are application-owned semantic identifiers.

The package accepts:

``` text
string
BackedEnum
```

and normalizes to a string payload type.

The package does not impose a global notification type enum.
