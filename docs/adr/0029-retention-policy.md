# ADR-0029 --- Conservative independent retention policies

Status: **Accepted**

Persistent notification inbox pruning is disabled by default.

Delivery tracking defaults to 90-day retention when enabled.

Unread notifications are not automatically pruned by the default inbox
policy.

Managed invalidated devices may be pruned after a configurable grace
period.

Use a unified explicit command:

``` bash
php artisan notifications:prune
```

with dry-run support.
