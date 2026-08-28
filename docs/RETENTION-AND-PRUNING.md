# Retention and Pruning Specification

## Status

Draft v0.1 --- accepted architectural direction.

## Purpose

Define independent retention policies for:

``` text
persistent notification inbox
delivery tracking
managed devices
operational metadata
```

Retention is application-configurable and must never silently delete
durable user notifications by default.

## Persistent notification inbox

Default:

``` text
automatic pruning = disabled
```

Rationale:

-   notifications are user-visible history;
-   applications have different audit requirements;
-   automatic deletion can surprise consumers.

Configuration:

``` php
'retention' => [
    'notifications' => [
        'enabled' => false,
        'days' => null,
        'only_read' => true,
    ],
],
```

If enabled, recommended default behavior:

``` text
prune only read notifications older than N days
```

Unread notifications should not be deleted by default.

## Delivery tracking

Default:

``` text
enabled when tracking feature is enabled
retention_days = 90
```

Delivery tracking is operational data and can grow rapidly.

## Managed devices

No age-based automatic deletion by default.

Invalidated devices may be pruned after a configurable grace period.

Example:

``` php
'devices' => [
    'prune_invalidated_after_days' => 30,
],
```

## Pruning command

Primary operational command:

``` bash
php artisan notifications:prune
```

Recommendation:

Use one command with scoped options rather than multiple commands.

Examples:

``` bash
php artisan notifications:prune
php artisan notifications:prune --only=deliveries
php artisan notifications:prune --only=notifications
php artisan notifications:prune --dry-run
```

This supersedes the earlier proposed dedicated
`notifications:prune-deliveries` command.

## Dry-run

`--dry-run` is strongly recommended.

Example:

``` text
Notification Orchestrator Prune

Notifications
0 rows would be removed

Deliveries
18,422 rows would be removed

Invalidated devices
31 rows would be removed

Dry run: no data changed
```

## Scheduler integration

Applications may schedule:

``` php
Schedule::command('notifications:prune')->daily();
```

The package should not automatically install a scheduler entry.

## Batch deletion

Pruning must operate in chunks to avoid:

``` text
long transactions
large locks
memory spikes
```

## Notification pruning rules

When enabled:

``` text
created_at older than threshold
AND read_at IS NOT NULL
```

unless application explicitly opts into another strategy.

Potential extension contract:

``` php
interface NotificationRetentionPolicy
{
    public function pruneQuery(Builder $query): Builder;
}
```

Not required for minimal implementation if config handles initial use
cases.

## Delivery pruning rules

Typical:

``` text
created_at older than retention threshold
```

Applications may preserve failed deliveries longer in future.

Initial model keeps one retention period for simplicity.

## Referential integrity

Pruning a persistent notification must handle related delivery records safely.
Tracking `notification_id` is logical and is not a foreign key to the inbox
primary key. Use explicit cleanup scoped to the logical ID from the row's
payload AND its notifiable type/ID. Never delete every recipient's deliveries
just because one recipient's inbox row was pruned.

See [ADR-0032](adr/0032-notification-identity.md). Tracking may exist without an
inbox row and remains independently eligible for delivery retention.

Delivery tracking retention may delete delivery rows while the
persistent notification remains.

## Status integration

`notifications:status` reports:

``` text
Retention
notifications: disabled
deliveries: 90 days
invalid devices: 30 days
```

## Observability

Prune operations should log summary counts, not deleted payload
contents.

Potential lifecycle events:

``` text
NotificationPruningStarted
NotificationPruningCompleted
```

No per-row events for bulk pruning by default.

## Safety

Pruning configuration changes do not immediately delete data.

Deletion only occurs when the explicit prune command runs.

This prevents a configuration deployment from unexpectedly deleting
historical records.
