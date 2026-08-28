# Observability and Logging Specification

## Status

Draft v0.1 --- accepted architectural direction.

## Objective

Make notification behavior traceable across:

``` text
request/domain event
orchestration
recipient resolution
planning
queue
channel/provider
tracking
realtime synchronization
```

without requiring a specific observability vendor.

## Correlation identity

Every logical notification dispatch has:

``` text
notification_id
correlation_id
```

For v0.1, correlation ID may default to the notification UUID unless a
separate ID is useful.

Here and in tracking, `notification_id` is the logical ID shared across
recipients. Use the explicit key `stored_notification_id` when additionally
logging a personal inbox ID. Do not confuse these with the personal
`notification_id` used by read/unread API events and push projections.
See [ADR-0032](adr/0032-notification-identity.md).

Every delivery job carries:

``` text
notification_id
correlation_id
recipient identity
channel
delivery_id where tracking enabled
```

## Structured logging

Use Laravel logging.

Recommended structured context:

``` php
[
    'notification_id' => $id,
    'correlation_id' => $correlationId,
    'type' => $type,
    'channel' => $channel,
    'provider' => $provider,
]
```

Do not log entire payloads by default.

## Log levels

Recommended:

``` text
debug
- planning details
- skipped-channel reason
- resolver counts

info
- dispatch accepted
- prune summaries
- configuration/status summaries when explicitly invoked

warning
- temporary provider issue
- degraded health
- retry scheduled

error
- permanent delivery failure
- invalid runtime transition
- provider failure after retries

critical
- reserved for package-level unusable infrastructure where appropriate
```

Configuration errors typically throw explicit exceptions rather than
merely logging.

Planning logs/results must not claim persistence or enqueue. A
`plannedQueueJobCount` is intent only; persisted tracking, queued and sent
lifecycle events occur at execution after commit when applicable. The fake
likewise records only at that boundary. After-commit failures cannot undo
business commits and callbacks alone do not guarantee crash recovery.
See [ADR-0034](adr/0034-planning-and-after-commit-execution.md).

## Lifecycle events

Public/internal Laravel events provide observability hooks.

Core candidates:

``` text
NotificationDispatching
NotificationDispatched

DeliveryPlanned
DeliveryQueued
DeliveryProcessing
DeliverySent
DeliveryDelivered
DeliveryFailed
DeliverySkipped

NotificationMarkedRead
NotificationMarkedUnread
NotificationsMarkedAllRead

DeviceRegistered
DeviceReassigned
DeviceInvalidated
```

Context:

``` text
ContextDeliveryPlanned
ContextDeliverySent
ContextDeliveryFailed
```

Avoid excessive event surface before v1.0; document only events intended
for public consumption.

## Metrics abstraction

Do not require Prometheus/OpenTelemetry.

Expose events and/or optional metrics contract:

``` php
interface NotificationMetrics
{
    public function increment(string $metric, array $tags = []): void;
    public function timing(string $metric, float $milliseconds, array $tags = []): void;
}
```

Default implementation:

``` text
NullNotificationMetrics
```

Applications can adapt to:

``` text
Prometheus
OpenTelemetry
StatsD
custom telemetry
```

The metrics contract may remain internal in v0.1 if public stability is
uncertain.

## Recommended metrics

``` text
notifications.dispatched
notifications.recipients
deliveries.planned
deliveries.sent
deliveries.failed
deliveries.skipped
delivery.duration
queue.delay
push.invalid_destination
mqtt.publish_failed
notifications.read
```

Tags should have bounded cardinality.

Good:

``` text
channel=push
provider=fcm
type=incident.created
```

Avoid unbounded:

``` text
user_id
notification_id
device_token
topic
```

in metrics labels.

## Status command

`notifications:status` is diagnostic, not monitoring.

It checks current configuration/health.

Long-term monitoring should consume logs/events/metrics.

## Delivery failures

Sanitized error details may be stored in tracking:

``` text
error_code
error_message
```

Logs should reference `delivery_id`/`correlation_id`.

## Queue observability

Laravel queue remains authoritative for worker lifecycle.

The package should integrate with jobs without replacing Laravel Horizon
or other queue monitoring tools.

If Redis/Horizon is used later, no package rewrite is needed.

## Realtime observability

A successful broadcast means:

``` text
event handed to configured broadcaster
```

not:

``` text
every browser rendered the event
```

Logs/status terminology must reflect that.

## MQTT observability

A successful publish means broker-level acceptance according to client
semantics, not per-subscriber UI rendering.

## Push observability

A successful provider send means provider acceptance unless the provider
gives stronger confirmation.

## Privacy

Redaction requirements apply to:

``` text
logs
exceptions
status output
delivery metadata
metrics
```

## Debug mode

Potential configuration:

``` php
'observability' => [
    'debug' => false,
]
```

When enabled, include additional safe planning information but still
never secrets.

No raw provider tokens even in debug mode.
