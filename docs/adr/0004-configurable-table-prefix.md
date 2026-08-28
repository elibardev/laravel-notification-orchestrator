# ADR-0004 — Configurable table prefix and names

Status: **Accepted**

## Context

The package must coexist with existing applications and naming conventions.

## Decision

All package-owned tables use a centralized table-name resolver supporting:

1. global prefix;
2. canonical logical table name;
3. explicit per-table override.

Example:

```php
'table_prefix' => 'notify_'
```

produces:

```text
notify_notifications
notify_preferences
notify_devices
notify_deliveries
```

An explicit table name overrides the generated name.

## Constraint

No package component may construct table names independently.
